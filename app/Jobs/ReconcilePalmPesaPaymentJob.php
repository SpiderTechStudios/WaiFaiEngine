<?php

namespace App\Jobs;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Services\PlatformPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcilePalmPesaPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $platformPaymentId) {}

    public function handle(PlatformPaymentService $platformPaymentService): void
    {
        $payment = PlatformPayment::query()->find($this->platformPaymentId);

        if (! $payment) {
            return;
        }

        if ($payment->provider_slug !== PaymentProvider::SLUG_PALMPESA) {
            return;
        }

        if ($payment->status !== PlatformPayment::STATUS_PENDING) {
            return;
        }

        try {
            $platformPaymentService->reconcileProviderPayment($payment);
        } catch (\Throwable $e) {
            Log::warning('PalmPesa payment reconcile failed', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
