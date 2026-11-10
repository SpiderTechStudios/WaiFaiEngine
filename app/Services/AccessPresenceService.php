<?php

namespace App\Services;

use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use Illuminate\Support\Facades\DB;

/**
 * Presence (online/offline) for WiFiDog-based routers.
 *
 * A connected client keeps sending /auth counters, which updates
 * network_sessions.last_activity_at. When that stops, the client left the
 * hotspot; mark it offline without touching its access grant (the grant may
 * still be valid, so returning later resumes access).
 */
class AccessPresenceService
{
    /**
     * @return array{network_sessions: int, captive_sessions: int}
     */
    public function expireStale(): array
    {
        $cutoff = now()->subMinutes(max(1, (int) config('captive.presence_timeout_minutes', 5)));

        $staleSessions = NetworkSession::query()
            ->where('status', 'active')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', $cutoff)
            ->whereHas('networkDevice', function ($query): void {
                $query->whereIn('gateway_type', [
                    NetworkDevice::GATEWAY_RUIJIE,
                    NetworkDevice::GATEWAY_WAVLINK,
                ]);
            })
            ->get();

        $ended = 0;
        $disconnected = 0;

        foreach ($staleSessions as $session) {
            DB::transaction(function () use ($session, &$ended, &$disconnected): void {
                $updated = NetworkSession::query()
                    ->whereKey($session->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'ended',
                        'ended_at' => now(),
                    ]);

                if ($updated === 0) {
                    return;
                }

                $ended++;

                $disconnected += CaptiveSession::query()
                    ->where('network_session_id', $session->id)
                    ->where('status', CaptiveSession::STATUS_AUTHENTICATED)
                    ->update([
                        'status' => CaptiveSession::STATUS_DISCONNECTED,
                        'last_seen_at' => $session->last_activity_at,
                    ]);
            });
        }

        return [
            'network_sessions' => $ended,
            'captive_sessions' => $disconnected,
        ];
    }
}
