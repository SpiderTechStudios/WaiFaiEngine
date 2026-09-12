<?php

namespace App\Console\Commands;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Services\PlatformPaymentService;
use Illuminate\Console\Command;

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

        foreach ($payments as $payment) {
            $updated = $platformPaymentService->reconcileProviderPayment($payment);
            if ($updated->status !== PlatformPayment::STATUS_PENDING) {
                $reconciled++;
            }
        }

        $this->info("Checked {$payments->count()} PalmPesa payment(s); resolved {$reconciled}.");

        return self::SUCCESS;
    }
}
