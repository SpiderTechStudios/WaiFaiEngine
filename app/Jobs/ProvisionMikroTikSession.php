<?php

namespace App\Jobs;

use App\Models\CaptiveSession;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionMikroTikSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $captiveSessionId) {}

    public function handle(MikroTikService $mikroTikService): void
    {
        $session = CaptiveSession::query()
            ->with(['networkDevice', 'accessGrant.internetPlan'])
            ->find($this->captiveSessionId);

        if (! $session || ! $session->isAuthenticated()) {
            return;
        }

        try {
            $mikroTikService->ensureProvisioned($session);
        } catch (\Throwable $exception) {
            // Payment is already committed. Provisioning is retried when the
            // browser opens /gateway/mikrotik/{token} and by the sync command.
            Log::warning('MikroTik provisioning job failed', [
                'captive_session_id' => $session->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
