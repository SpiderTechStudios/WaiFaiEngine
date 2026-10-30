<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Models\PlatformPayment;
use Illuminate\Console\Command;

class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire-pending
                            {--minutes= : Minutes a payment may stay pending before expiring}';

    protected $description = 'Expire abandoned pending customer/portal payments so they stop counting as pending';

    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('platform.pending_payment_ttl_minutes', 30));
        $cutoff = now()->subMinutes(max(1, $minutes));

        $transactions = PaymentTransaction::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($transactions as $transaction) {
            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $metadata['expired_at'] = now()->toIso8601String();
            $metadata['expiry_reason'] = 'abandoned';

            $transaction->forceFill([
                'status' => 'expired',
                'metadata' => $metadata,
            ])->save();

            $platformId = (int) ($metadata['platform_payment_id'] ?? 0);
            if ($platformId > 0) {
                PlatformPayment::query()
                    ->whereKey($platformId)
                    ->where('status', PlatformPayment::STATUS_PENDING)
                    ->update([
                        'status' => PlatformPayment::STATUS_EXPIRED,
                        'cancelled_at' => now(),
                    ]);
            }
        }

        // Orphan portal collections (no tenant payment left) also expire.
        $platformExpired = PlatformPayment::query()
            ->where('purpose', PlatformPayment::PURPOSE_HOTSPOT_PORTAL)
            ->where('status', PlatformPayment::STATUS_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->update([
                'status' => PlatformPayment::STATUS_EXPIRED,
                'cancelled_at' => now(),
            ]);

        $this->info(sprintf(
            'Expired %d customer payment(s) and %d portal collection(s) pending for over %d minutes.',
            $transactions->count(),
            $platformExpired,
            $minutes,
        ));

        return self::SUCCESS;
    }
}
