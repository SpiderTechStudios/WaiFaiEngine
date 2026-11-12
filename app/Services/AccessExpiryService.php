<?php

namespace App\Services;

use App\Jobs\RevokeGatewaySession;
use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\NetworkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccessExpiryService
{
    /**
     * Mark due access grants as expired and clear their live sessions.
     * Customer records are never deleted.
     *
     * @return array{grants: int, network_sessions: int, captive_sessions: int}
     */
    public function expireDue(): array
    {
        $grants = 0;
        $networkSessions = 0;
        $captiveSessions = 0;

        AccessGrant::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$grants, &$networkSessions, &$captiveSessions): void {
                foreach ($chunk as $grant) {
                    $result = $this->expireGrant($grant);
                    $grants += $result['grant'] ? 1 : 0;
                    $networkSessions += $result['network_sessions'];
                    $captiveSessions += $result['captive_sessions'];
                }
            });

        // Catch any leftover live sessions whose grant is already expired.
        $networkSessions += $this->endSessionsForExpiredGrants();
        $captiveSessions += $this->expireCaptiveSessionsForExpiredGrants();

        Log::info('access.expire_due', [
            'grants' => $grants,
            'network_sessions' => $networkSessions,
            'captive_sessions' => $captiveSessions,
        ]);

        return [
            'grants' => $grants,
            'network_sessions' => $networkSessions,
            'captive_sessions' => $captiveSessions,
        ];
    }

    /**
     * Expire one grant and clear sessions tied to it.
     *
     * @return array{grant: bool, network_sessions: int, captive_sessions: int}
     */
    public function expireGrant(AccessGrant $grant): array
    {
        $captiveSessionIds = CaptiveSession::query()
            ->where('access_grant_id', $grant->id)
            ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
            ->pluck('id')
            ->all();

        $result = DB::transaction(function () use ($grant) {
            $grant = AccessGrant::query()->whereKey($grant->id)->lockForUpdate()->first();

            if (! $grant) {
                return ['grant' => false, 'network_sessions' => 0, 'captive_sessions' => 0];
            }

            $markedGrant = false;

            if ($grant->status === 'active') {
                $grant->forceFill(['status' => 'expired'])->save();
                $markedGrant = true;
            }

            $networkSessions = NetworkSession::query()
                ->where('access_grant_id', $grant->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                ]);

            $captiveSessions = CaptiveSession::query()
                ->where('access_grant_id', $grant->id)
                ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
                ->update([
                    'status' => CaptiveSession::STATUS_EXPIRED,
                ]);

            return [
                'grant' => $markedGrant,
                'network_sessions' => $networkSessions,
                'captive_sessions' => $captiveSessions,
            ];
        });

        foreach ($captiveSessionIds as $captiveSessionId) {
            DB::afterCommit(fn () => RevokeGatewaySession::dispatch((int) $captiveSessionId));
        }

        return $result;
    }

    private function endSessionsForExpiredGrants(): int
    {
        $query = NetworkSession::query()
            ->where('status', 'active')
            ->whereHas('accessGrant', function ($query): void {
                $query->where('status', 'expired')
                    ->orWhere(function ($inner): void {
                        $inner->where('status', 'active')
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });
            });

        $networkSessionIds = (clone $query)->pluck('id');

        $captiveSessionIds = CaptiveSession::query()
            ->whereIn('network_session_id', $networkSessionIds)
            ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
            ->pluck('id');

        $updated = $query->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        foreach ($captiveSessionIds as $captiveSessionId) {
            RevokeGatewaySession::dispatch((int) $captiveSessionId);
        }

        return $updated;
    }

    private function expireCaptiveSessionsForExpiredGrants(): int
    {
        // The access grant is the source of truth. Repair any live session
        // whose cached expiry is shorter than its still-active grant (for
        // example the pending-session TTL) before deciding who to disconnect.
        $this->syncLiveSessionExpiryToGrants();

        $query = CaptiveSession::query()
            ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
            ->where(function ($query): void {
                $query->whereHas('accessGrant', function ($grant): void {
                    $grant->where('status', '!=', 'active')
                        ->orWhere(function ($inner): void {
                            $inner->whereNotNull('expires_at')
                                ->where('expires_at', '<=', now());
                        });
                })->orWhere(function ($legacy): void {
                    // Sessions without a grant (pending/legacy) fall back to
                    // their own expiry.
                    $legacy->whereNull('access_grant_id')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                });
            });

        $captiveSessionIds = (clone $query)->pluck('id');

        $updated = $query->update([
            'status' => CaptiveSession::STATUS_EXPIRED,
        ]);

        foreach ($captiveSessionIds as $captiveSessionId) {
            RevokeGatewaySession::dispatch((int) $captiveSessionId);
        }

        return $updated;
    }

    /**
     * Keep authenticated captive sessions' cached expiry in sync with the
     * still-active access grant so access is never removed early.
     */
    private function syncLiveSessionExpiryToGrants(): void
    {
        CaptiveSession::query()
            ->with('accessGrant')
            ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
            ->whereNotNull('access_grant_id')
            ->whereHas('accessGrant', function ($grant): void {
                $grant->where('status', 'active')
                    ->where(function ($inner): void {
                        $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->chunkById(200, function ($sessions): void {
                foreach ($sessions as $session) {
                    $grant = $session->accessGrant;

                    if (! $grant || ! $grant->isUsable()) {
                        continue;
                    }

                    if ($session->expires_at === null && $grant->expires_at === null) {
                        continue;
                    }

                    if ($session->expires_at !== null
                        && $grant->expires_at !== null
                        && $session->expires_at->equalTo($grant->expires_at)) {
                        continue;
                    }

                    $session->forceFill(['expires_at' => $grant->expires_at])->save();
                }
            });
    }
}
