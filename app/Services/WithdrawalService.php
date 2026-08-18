<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Company $company, array $data, User $actor): Withdrawal
    {
        $currency = $data['currency'] ?? 'TZS';
        $wallet = Wallet::query()->firstOrCreate(
            ['company_id' => $company->id, 'currency' => $currency],
            ['balance' => 0, 'status' => 'active'],
        );

        if ($wallet->balance < $data['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient wallet balance.'],
            ]);
        }

        return DB::transaction(function () use ($company, $wallet, $data, $actor) {
            $before = $wallet->balance;
            $wallet->forceFill(['balance' => $wallet->balance - $data['amount']])->save();

            $withdrawal = Withdrawal::query()->create([
                'company_id' => $company->id,
                'wallet_id' => $wallet->id,
                'requested_by' => $actor->id,
                'provider' => $data['provider'],
                'destination_phone' => $data['destination_phone'],
                'destination_name' => $data['destination_name'] ?? null,
                'reference' => 'WD-'.strtoupper(Str::random(10)),
                'amount' => $data['amount'],
                'currency' => $wallet->currency,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $data['amount'],
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'reference_type' => Withdrawal::class,
                'reference_id' => $withdrawal->id,
                'description' => 'Withdrawal '.$withdrawal->reference,
            ]);

            $this->auditLogger->log('withdrawal_requested', $actor, $company->id, Withdrawal::class, $withdrawal->id);

            return $withdrawal->load('wallet');
        });
    }
}
