<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Models\PaymentTransaction;
use App\Models\PayoutAccount;
use App\Models\PlatformPayment;
use App\Models\Withdrawal;
use App\Services\PaymentMethodResolver;
use Illuminate\Console\Command;

class BackfillPaymentMethodsCommand extends Command
{
    protected $signature = 'payment-methods:backfill
        {--dry-run : Report what would change without writing}
        {--chunk=500 : Rows per batch}
        {--table= : Only backfill one table (payment_transactions|platform_payments|payment_attempts|withdrawals|payout_accounts)}';

    protected $description = 'Backfill payment_method_id on payments and withdrawals from legacy network strings and phone numbers';

    public function handle(PaymentMethodResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $only = (string) $this->option('table');

        $targets = [
            'payment_transactions' => fn () => $this->backfillPaymentTransactions($resolver, $chunk, $dryRun),
            'platform_payments' => fn () => $this->backfillPlatformPayments($resolver, $chunk, $dryRun),
            'payment_attempts' => fn () => $this->backfillPaymentAttempts($resolver, $chunk, $dryRun),
            'withdrawals' => fn () => $this->backfillWithdrawals($resolver, $chunk, $dryRun),
            'payout_accounts' => fn () => $this->backfillPayoutAccounts($resolver, $chunk, $dryRun),
        ];

        if ($only !== '' && ! isset($targets[$only])) {
            $this->error('Unknown table: '.$only);

            return self::FAILURE;
        }

        foreach ($targets as $name => $handler) {
            if ($only !== '' && $only !== $name) {
                continue;
            }

            $this->info(($dryRun ? '[dry-run] ' : '')."Backfilling {$name}…");
            $handler();
        }

        return self::SUCCESS;
    }

    private function backfillPaymentTransactions(PaymentMethodResolver $resolver, int $chunk, bool $dryRun): void
    {
        $mapped = 0;
        $unmapped = 0;
        $samples = [];

        PaymentTransaction::query()
            ->with('customer:id,phone')
            ->whereNull('payment_method_id')
            ->chunkById($chunk, function ($rows) use ($resolver, $dryRun, &$mapped, &$unmapped, &$samples) {
                foreach ($rows as $row) {
                    $id = $resolver->idFor($row->payment_method, $row->customer?->phone);

                    if ($id === null) {
                        $unmapped++;
                        if (count($samples) < 10) {
                            $samples[] = ['id' => $row->id, 'value' => $row->payment_method, 'phone' => $row->customer?->phone];
                        }

                        continue;
                    }

                    $mapped++;
                    if (! $dryRun) {
                        PaymentTransaction::query()->whereKey($row->id)->update(['payment_method_id' => $id]);
                    }
                }
            });

        $this->report($mapped, $unmapped, $samples);
    }

    private function backfillPlatformPayments(PaymentMethodResolver $resolver, int $chunk, bool $dryRun): void
    {
        $mapped = 0;
        $unmapped = 0;
        $samples = [];

        PlatformPayment::query()
            ->whereNull('payment_method_id')
            ->chunkById($chunk, function ($rows) use ($resolver, $dryRun, &$mapped, &$unmapped, &$samples) {
                foreach ($rows as $row) {
                    $metadata = $row->metadata ?? [];
                    $value = $metadata['network']
                        ?? data_get($metadata, 'provider_charge.raw.network')
                        ?? data_get($metadata, 'provider_charge.raw.channel')
                        ?? $row->payment_method;

                    $id = $resolver->idFor(is_string($value) ? $value : null, $row->phone);

                    if ($id === null) {
                        $unmapped++;
                        if (count($samples) < 10) {
                            $samples[] = ['id' => $row->id, 'value' => $value, 'phone' => $row->phone];
                        }

                        continue;
                    }

                    $mapped++;
                    if (! $dryRun) {
                        PlatformPayment::query()->whereKey($row->id)->update(['payment_method_id' => $id]);
                    }
                }
            });

        $this->report($mapped, $unmapped, $samples);
    }

    private function backfillPaymentAttempts(PaymentMethodResolver $resolver, int $chunk, bool $dryRun): void
    {
        $mapped = 0;
        $unmapped = 0;
        $samples = [];

        PaymentAttempt::query()
            ->whereNull('payment_method_id')
            ->chunkById($chunk, function ($rows) use ($resolver, $dryRun, &$mapped, &$unmapped, &$samples) {
                foreach ($rows as $row) {
                    $payload = $row->response_payload ?? $row->request_payload ?? [];
                    $value = $payload['network']
                        ?? $payload['channel']
                        ?? data_get($payload, 'data.network')
                        ?? data_get($payload, 'data.channel')
                        ?? data_get($payload, 'data.0.channel');

                    $id = $resolver->idFor(is_string($value) ? $value : null, $row->phone_number);

                    if ($id === null) {
                        $unmapped++;
                        if (count($samples) < 10) {
                            $samples[] = ['id' => $row->id, 'value' => $value, 'phone' => $row->phone_number];
                        }

                        continue;
                    }

                    $mapped++;
                    if (! $dryRun) {
                        PaymentAttempt::query()->whereKey($row->id)->update(['payment_method_id' => $id]);
                    }
                }
            });

        $this->report($mapped, $unmapped, $samples);
    }

    private function backfillWithdrawals(PaymentMethodResolver $resolver, int $chunk, bool $dryRun): void
    {
        $mapped = 0;
        $unmapped = 0;
        $samples = [];

        Withdrawal::query()
            ->whereNull('payment_method_id')
            ->chunkById($chunk, function ($rows) use ($resolver, $dryRun, &$mapped, &$unmapped, &$samples) {
                foreach ($rows as $row) {
                    $value = $row->network ?: $row->provider;
                    $id = $resolver->idFor($value, $row->destination_phone);

                    if ($id === null) {
                        $unmapped++;
                        if (count($samples) < 10) {
                            $samples[] = ['id' => $row->id, 'value' => $value, 'phone' => $row->destination_phone];
                        }

                        continue;
                    }

                    $mapped++;
                    if (! $dryRun) {
                        Withdrawal::query()->whereKey($row->id)->update(['payment_method_id' => $id]);
                    }
                }
            });

        $this->report($mapped, $unmapped, $samples);
    }

    private function backfillPayoutAccounts(PaymentMethodResolver $resolver, int $chunk, bool $dryRun): void
    {
        $mapped = 0;
        $unmapped = 0;
        $samples = [];

        PayoutAccount::query()
            ->whereNull('payment_method_id')
            ->chunkById($chunk, function ($rows) use ($resolver, $dryRun, &$mapped, &$unmapped, &$samples) {
                foreach ($rows as $row) {
                    $id = $resolver->idFor($row->network, $row->phone);

                    if ($id === null) {
                        $unmapped++;
                        if (count($samples) < 10) {
                            $samples[] = ['id' => $row->id, 'value' => $row->network, 'phone' => $row->phone];
                        }

                        continue;
                    }

                    $mapped++;
                    if (! $dryRun) {
                        PayoutAccount::query()->whereKey($row->id)->update(['payment_method_id' => $id]);
                    }
                }
            });

        $this->report($mapped, $unmapped, $samples);
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     */
    private function report(int $mapped, int $unmapped, array $samples): void
    {
        $this->line("  mapped: {$mapped}, unmapped (left null): {$unmapped}");

        foreach ($samples as $sample) {
            $this->warn('  unmapped sample: '.json_encode($sample));
        }
    }
}
