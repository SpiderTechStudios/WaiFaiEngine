<?php

namespace App\Console\Commands;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Services\PlatformPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePalmPesaPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-palmpesa
                            {--minutes= : Minutes a payment must stay pending before status polling}';

    protected $description = 'Poll PalmPesa order-status for payments still pending after the configured wait';

    public function handle(PlatformPaymentService $platformPaymentService): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('services.palmpesa.status_check_minutes', 4));
        $cutoff = now()->subMinutes(max(1, $minutes));

        $payments = PlatformPayment::query()
            ->where('status', PlatformPayment::STATUS_PENDING)
            ->where('initiated_at', '<=', $cutoff)
            ->whereNotNull('external_reference')
            ->whereHas('provider', function ($query) {
                $query->where('slug', PaymentProvider::SLUG_PALMPESA)
                    ->orWhere('settings->driver', PaymentProvider::SLUG_PALMPESA);
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        $reconciled = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            try {
                $updated = $platformPaymentService->reconcileProviderPayment($payment);
                if ($updated->status !== PlatformPayment::STATUS_PENDING) {
                    $reconciled++;
                }
            } catch (\Throwable $e) {
                // Provider outage / bad response: keep the payment pending and try again later.
                $skipped++;
                Log::warning('PalmPesa reconcile skipped', [
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "Checked {$payments->count()} PalmPesa payment(s); resolved {$reconciled}.";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} awaiting provider availability.";
        }
        $this->info($message);

        return self::SUCCESS;
    }
}
