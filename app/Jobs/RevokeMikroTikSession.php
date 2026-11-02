<?php

namespace App\Jobs;

use App\Models\CaptiveSession;
use App\Models\NetworkSession;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RevokeMikroTikSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $captiveSessionId) {}

    public function handle(MikroTikService $mikroTikService): void
    {
        $session = CaptiveSession::query()
            ->with('networkDevice')
            ->find($this->captiveSessionId);

        if (! $session) {
            return;
        }

        try {
            $mikroTikService->revoke($session);
        } catch (\Throwable $exception) {
            // Do not fail access expiry / the queue because a router is down.
            // The mikrotik:sync command is the backstop.
            Log::warning('MikroTik revocation job failed', [
                'captive_session_id' => $session->id,
                'error' => $exception->getMessage(),
            ]);

            return;
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
