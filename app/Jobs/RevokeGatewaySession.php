<?php

namespace App\Jobs;

use App\Models\CaptiveSession;
use App\Models\NetworkSession;
use App\Services\GatewayAccessRevoker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RevokeGatewaySession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $captiveSessionId) {}

    public function handle(GatewayAccessRevoker $revoker): void
    {
        $session = CaptiveSession::query()
            ->with('networkDevice')
            ->find($this->captiveSessionId);

        if (! $session) {
            return;
        }

        try {
            $result = $revoker->revoke($session);
        } catch (\Throwable $exception) {
            // Do not fail access expiry / the queue because a router is down.
            Log::warning('gateway_session_revocation_failed', [
                'captive_session_id' => $session->id,
                'router_id' => $session->network_device_id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (($result['revoked'] ?? false) === false) {
            Log::warning('gateway_session_not_revoked', [
                'captive_session_id' => $session->id,
                'router_id' => $session->network_device_id,
                'result' => $result,
            ]);
        }

        if ($session->network_session_id) {
            NetworkSession::query()
                ->whereKey($session->network_session_id)
                ->where('status', 'active')
                ->update(['status' => 'ended', 'ended_at' => now()]);
        }

        if ($session->status === CaptiveSession::STATUS_AUTHENTICATED) {
            $session->forceFill(['status' => CaptiveSession::STATUS_DISCONNECTED])->save();
        }

        $session->forceFill([
            'mikrotik_username' => null,
            'mikrotik_password' => null,
            'mikrotik_user_id' => null,
            'mikrotik_grant_id' => null,
            'mikrotik_provisioned_at' => null,
            'router_active_id' => null,
        ])->save();
    }
}
