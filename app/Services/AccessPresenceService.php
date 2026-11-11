<?php

namespace App\Services;

/**
 * Presence tracking for WiFiDog-based routers.
 *
 * Paid and voucher sessions are time-based entitlements, not activity-based.
 * A client that stops sending /auth counters (phone sleep, Wi-Fi power saving,
 * brief drop, AP counter interval) must NOT lose access before its access grant
 * expires.
 *
 * Access is only ended by AccessExpiryService when the grant expires. Presence
 * is therefore intentionally a no-op; the access:presence command is kept for
 * backwards compatibility and scheduled-job deployments.
 */
class AccessPresenceService
{
    /**
     * @return array{network_sessions: int, captive_sessions: int}
     */
    public function expireStale(): array
    {
        return [
            'network_sessions' => 0,
            'captive_sessions' => 0,
        ];
    }
}
