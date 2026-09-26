<?php

namespace App\Services;

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
        return DB::transaction(function () use ($grant) {
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
    }

    private function endSessionsForExpiredGrants(): int
    {
        return NetworkSession::query()
            ->where('status', 'active')
            ->whereHas('accessGrant', function ($query): void {
                $query->where('status', 'expired')
                    ->orWhere(function ($inner): void {
                        $inner->where('status', 'active')
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });
            })
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);
    }

    private function expireCaptiveSessionsForExpiredGrants(): int
    {
        return CaptiveSession::query()
            ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                })->orWhereHas('accessGrant', function ($grant): void {
                    $grant->where('status', 'expired')
                        ->orWhere(function ($inner): void {
                            $inner->where('status', 'active')
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<=', now());
                        });
                });
            })
            ->update([
                'status' => CaptiveSession::STATUS_EXPIRED,
            ]);
    }
}
