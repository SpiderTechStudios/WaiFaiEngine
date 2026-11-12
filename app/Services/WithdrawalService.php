<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PaymentProvider;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalOtpNotification;
use App\Payments\PaymentProviderManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private WithdrawalFeeService $feeService,
        private PaymentProviderManager $providerManager,
        private PaymentMethodResolver $paymentMethods,
    ) {}

    /**
     * Price a withdrawal without creating it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(Company $company, array $data): array
    {
        $provider = $this->resolvePayoutProvider($data['payment_provider_slug'] ?? null);

        $account = $this->resolvePayoutAccountForPreview($company, $data);
        $phone = $account?->phone ?? (string) ($data['destination_phone'] ?? $data['phone'] ?? '');

        $quote = $this->feeService->quote($provider, (float) $data['amount'], $phone);

        $wallet = Wallet::query()
            ->where('company_id', $company->id)
            ->where('currency', $quote['currency'])
            ->first();

        $balance = (float) ($wallet?->balance ?? 0);

        return [
            ...$quote,
            'wallet_balance' => $balance,
            'sufficient_balance' => $balance >= $quote['amount'],
            'payout_account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'phone' => $account->phone,
                'network' => $account->network,
            ] : null,
        ];
    }

    /**
     * Create a pending withdrawal and send the confirmation OTP.
     *
     * The wallet is debited immediately (a hold). If the withdrawal is cancelled
     * or the payout fails, the gross amount is credited back.
     *
     * @param  array<string, mixed>  $data
     */
    public function request(Company $company, array $data, User $actor): Withdrawal
    {
        $provider = $this->resolvePayoutProvider($data['payment_provider_slug'] ?? null);
        $account = $this->resolvePayoutAccount($company, $data);

        $quote = $this->feeService->quote($provider, (float) $data['amount'], $account->phone);
        $network = $account->network ?: ($quote['network'] ?: ($data['provider'] ?? null));
        $paymentMethod = $this->paymentMethods->resolve($network, $account->phone);

        $code = $this->generateOtp();

        $withdrawal = DB::transaction(function () use ($company, $provider, $account, $quote, $network, $paymentMethod, $code, $data, $actor) {
            $wallet = Wallet::query()
                ->where('company_id', $company->id)
                ->where('currency', $quote['currency'])
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::query()->create([
                    'company_id' => $company->id,
                    'currency' => $quote['currency'],
                    'balance' => 0,
                    'status' => 'active',
                ]);
            }

            $balanceBefore = (float) $wallet->balance;

            if ($balanceBefore < $quote['amount']) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient wallet balance.'],
                ]);
            }

            $wallet->forceFill(['balance' => $balanceBefore - $quote['amount']])->save();

            $withdrawal = Withdrawal::query()->create([
                'company_id' => $company->id,
                'wallet_id' => $wallet->id,
                'payout_account_id' => $account->id,
                'payment_provider_id' => $provider->id,
                'payment_provider_slug' => $provider->slug,
                'requested_by' => $actor->id,
                'provider' => $network ?: (string) ($data['provider'] ?? 'mobile_money'),
                'network' => $network,
                'payment_method_id' => $paymentMethod?->id,
                'destination_phone' => $account->phone,
                'destination_name' => $account->name,
                'reference' => 'WD-'.strtoupper(Str::random(10)),
                'amount' => $quote['amount'],
                'currency' => $quote['currency'],
                'platform_fee' => $quote['platform_fee'],
                'provider_fee' => $quote['provider_fee'],
                'fee_total' => $quote['fee_total'],
                'net_amount' => $quote['net_amount'],
                'fee_breakdown' => [
                    'platform' => [
                        'percent' => $quote['platform_percent'],
                        'fixed' => $quote['platform_fixed'],
                        'amount' => $quote['platform_fee'],
                    ],
                    'provider' => [
                        'slug' => $provider->slug,
                        'name' => $provider->name,
                        'percent' => $quote['provider_percent'],
                        'fixed' => $quote['provider_fixed'],
                        'amount' => $quote['provider_fee'],
                    ],
                    'total' => $quote['fee_total'],
                    'net_amount' => $quote['net_amount'],
                    'currency' => $quote['currency'],
                    'wallet_balance_before' => $balanceBefore,
                    'wallet_balance_after' => (float) $wallet->balance,
                ],
                'status' => Withdrawal::STATUS_PENDING,
                'otp_hash' => $this->hashOtp($code),
                'otp_expires_at' => $this->otpExpiresAt(),
                'otp_attempts' => 0,
                'otp_sent_at' => now(),
                'requested_at' => now(),
            ]);

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $quote['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'reference_type' => Withdrawal::class,
                'reference_id' => $withdrawal->id,
                'description' => 'Withdrawal '.$withdrawal->reference,
            ]);

            $this->auditLogger->log(
                'withdrawal_requested',
                $actor,
                $company->id,
                Withdrawal::class,
                $withdrawal->id,
                newValues: [
                    'amount' => $quote['amount'],
                    'net_amount' => $quote['net_amount'],
                    'platform_fee' => $quote['platform_fee'],
                    'provider_fee' => $quote['provider_fee'],
                    'payment_provider' => $provider->slug,
                    'network' => $network,
                ],
            );

            return $withdrawal;
        }, 3);

        $this->sendOtp($withdrawal, $actor, $code);

        return $withdrawal->fresh(['payoutAccount', 'paymentProvider', 'paymentMethod']);
    }

    /**
     * @deprecated Use request()
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): Withdrawal
    {
        return $this->request($company, $data, $actor);
    }

    public function confirm(Company $company, Withdrawal $withdrawal, string $otp, User $actor): Withdrawal
    {
        $withdrawal = $this->ownedWithdrawal($company, $withdrawal);

        if ($withdrawal->confirmed_at !== null || $withdrawal->status !== Withdrawal::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'withdrawal' => ['This withdrawal is already being processed.'],
            ]);
        }

        if ($withdrawal->otp_hash === null) {
            throw ValidationException::withMessages([
                'otp' => ['No confirmation code is available. Request a new one.'],
            ]);
        }

        if ($withdrawal->otp_expires_at !== null && now()->greaterThan($withdrawal->otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => ['This confirmation code has expired. Request a new one.'],
            ]);
        }

        $maxAttempts = max(1, (int) config('withdrawals.otp_max_attempts', 5));

        if ((int) $withdrawal->otp_attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Request a new confirmation code.'],
            ]);
        }

        if (! hash_equals((string) $withdrawal->otp_hash, $this->hashOtp($otp))) {
            $withdrawal->forceFill(['otp_attempts' => (int) $withdrawal->otp_attempts + 1])->save();

            throw ValidationException::withMessages([
                'otp' => ['The confirmation code is incorrect.'],
            ]);
        }

        DB::transaction(function () use ($company, $withdrawal) {
            $locked = Withdrawal::query()
                ->whereKey($withdrawal->id)
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->confirmed_at !== null || $locked->status !== Withdrawal::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['This withdrawal is already being processed.'],
                ]);
            }

            $locked->forceFill([
                'confirmed_at' => now(),
                'otp_hash' => null,
                'otp_expires_at' => null,
                'otp_attempts' => 0,
                'status' => Withdrawal::STATUS_PENDING_APPROVAL,
            ])->save();
        });

        $withdrawal = $withdrawal->fresh();

        $this->auditLogger->log(
            'withdrawal_confirmed',
            $actor,
            $company->id,
            Withdrawal::class,
            $withdrawal->id,
        );

        return $withdrawal->fresh(['payoutAccount', 'paymentProvider', 'paymentMethod']);
    }

    public function approve(Withdrawal $withdrawal, User $admin): Withdrawal
    {
        $withdrawal = DB::transaction(function () use ($withdrawal, $admin) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Withdrawal::STATUS_PENDING_APPROVAL || $locked->confirmed_at === null) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Only OTP-confirmed withdrawals can be approved.'],
                ]);
            }

            $locked->forceFill([
                'status' => Withdrawal::STATUS_PROCESSING,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ])->save();

            $this->auditLogger->log(
                'withdrawal_approved',
                $admin,
                $locked->company_id,
                Withdrawal::class,
                $locked->id,
            );

            return $locked->fresh();
        }, 3);

        return $this->startPayout($withdrawal, $admin);
    }

    /**
     * Approve a confirmed withdrawal without calling any payout provider.
     *
     * Used when the admin pays the client directly (cash/offline). The wallet is
     * debited exactly once: normally the debit hold was already created when the
     * client requested the withdrawal, so this only creates it for legacy rows
     * that never had one. The withdrawal is then marked completed.
     */
    public function approveManually(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $note) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Withdrawal::STATUS_PENDING_APPROVAL || $locked->confirmed_at === null) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Only OTP-confirmed withdrawals awaiting approval can be approved manually.'],
                ]);
            }

            $this->debitWalletIfNotDebited($locked, 'Withdrawal '.$locked->reference.' paid manually');

            $metadata = $locked->metadata ?? [];
            $metadata['payout'] = [
                'method' => 'manual',
                'provider' => null,
                'provider_reference' => null,
                'message' => 'Marked paid manually by admin (cash/offline). No payout provider was called.',
                'note' => $note,
                'requested_at' => now()->toIso8601String(),
            ];

            $locked->forceFill([
                'status' => Withdrawal::STATUS_COMPLETED,
                'approved_at' => $locked->approved_at ?? now(),
                'approved_by' => $admin->id,
                'processed_at' => now(),
                'confirmed_at' => $locked->confirmed_at ?? now(),
                'otp_hash' => null,
                'otp_expires_at' => null,
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->log(
                'withdrawal_approved_manually',
                $admin,
                $locked->company_id,
                Withdrawal::class,
                $locked->id,
                newValues: [
                    'amount' => (float) $locked->amount,
                    'method' => 'manual',
                    'note' => $note,
                ],
            );

            return $locked->fresh(['payoutAccount', 'paymentProvider', 'paymentMethod']);
        }, 3);
    }

    public function deny(Withdrawal $withdrawal, User $admin, string $reason): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Withdrawal::STATUS_PENDING_APPROVAL || $locked->confirmed_at === null) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Only OTP-confirmed withdrawals can be denied.'],
                ]);
            }

            $this->refundWallet($locked, 'Withdrawal denied '.$locked->reference);

            $locked->forceFill([
                'status' => Withdrawal::STATUS_DENIED,
                'denied_at' => now(),
                'denied_by' => $admin->id,
                'denial_reason' => Str::limit($reason, 1000, ''),
                'otp_hash' => null,
                'otp_expires_at' => null,
            ])->save();

            $this->auditLogger->log(
                'withdrawal_denied',
                $admin,
                $locked->company_id,
                Withdrawal::class,
                $locked->id,
                newValues: ['reason' => $reason],
            );

            return $locked->load(['payoutAccount', 'paymentProvider', 'paymentMethod']);
        }, 3);
    }

    private function startPayout(Withdrawal $withdrawal, User $actor): Withdrawal
    {
        $provider = PaymentProvider::query()->find($withdrawal->payment_provider_id)
            ?? PaymentProvider::query()->where('slug', $withdrawal->payment_provider_slug)->first();

        if (! $provider) {
            $this->failAndRefund($withdrawal, 'Payout provider is no longer available.');

            throw ValidationException::withMessages([
                'withdrawal' => ['Payout provider is no longer available.'],
            ]);
        }

        try {
            $driver = $this->providerManager->driverFor($provider);

            $result = $driver->initiatePayout([
                'amount' => (float) $withdrawal->net_amount,
                'currency' => (string) $withdrawal->currency,
                'phone' => (string) $withdrawal->destination_phone,
                'destination_phone' => (string) $withdrawal->destination_phone,
                'destination_name' => (string) $withdrawal->destination_name,
                'reference' => (string) $withdrawal->reference,
            ]);

            if (! $result->accepted) {
                throw ValidationException::withMessages([
                    'payout' => [$result->message ?: 'The payout provider rejected the request.'],
                ]);
            }

            $metadata = $withdrawal->metadata ?? [];
            $metadata['payout'] = [
                'provider' => $provider->slug,
                'provider_reference' => $result->providerReference,
                'message' => $result->message,
                'requested_at' => now()->toIso8601String(),
                'raw' => $result->raw,
            ];

            $withdrawal->forceFill([
                'external_reference' => $result->providerReference,
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->log(
                'withdrawal_payout_initiated',
                $actor,
                $withdrawal->company_id,
                Withdrawal::class,
                $withdrawal->id,
                newValues: [
                    'payment_provider' => $provider->slug,
                    'provider_reference' => $result->providerReference,
                    'net_amount' => (float) $withdrawal->net_amount,
                ],
            );
        } catch (\Throwable $e) {
            $this->failAndRefund($withdrawal->fresh(), $e->getMessage());

            if ($e instanceof ValidationException) {
                throw $e;
            }

            Log::warning('withdrawal_payout_failed', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'withdrawal' => ['The payout could not be started. Your wallet has been refunded.'],
            ]);
        }

        return $withdrawal->fresh(['payoutAccount', 'paymentProvider', 'paymentMethod']);
    }

    public function resendOtp(Company $company, Withdrawal $withdrawal, User $actor): Withdrawal
    {
        $withdrawal = $this->ownedWithdrawal($company, $withdrawal);

        if ($withdrawal->status !== Withdrawal::STATUS_PENDING || $withdrawal->confirmed_at !== null) {
            throw ValidationException::withMessages([
                'withdrawal' => ['This withdrawal is no longer awaiting confirmation.'],
            ]);
        }

        $cooldown = max(0, (int) config('withdrawals.otp_resend_seconds', 60));

        if ($withdrawal->otp_sent_at !== null && $withdrawal->otp_sent_at->diffInSeconds(now()) < $cooldown) {
            throw ValidationException::withMessages([
                'otp' => ['Please wait before requesting another confirmation code.'],
            ]);
        }

        $code = $this->generateOtp();

        $withdrawal->forceFill([
            'otp_hash' => $this->hashOtp($code),
            'otp_expires_at' => $this->otpExpiresAt(),
            'otp_attempts' => 0,
            'otp_sent_at' => now(),
        ])->save();

        $this->sendOtp($withdrawal, $actor, $code);

        return $withdrawal->fresh(['payoutAccount', 'paymentProvider', 'paymentMethod']);
    }

    /**
     * Cancel a pending withdrawal and return the held amount to the wallet.
     */
    public function cancel(Withdrawal $withdrawal, User $actor): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $actor) {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== Withdrawal::STATUS_PENDING || $withdrawal->confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Only pending withdrawals can be cancelled.'],
                ]);
            }

            $this->refundWallet($withdrawal, 'Withdrawal cancelled '.$withdrawal->reference);

            $withdrawal->forceFill([
                'status' => Withdrawal::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'otp_hash' => null,
                'otp_expires_at' => null,
            ])->save();

            $this->auditLogger->log('withdrawal_cancelled', $actor, $withdrawal->company_id, Withdrawal::class, $withdrawal->id);

            return $withdrawal->load(['wallet', 'paymentMethod']);
        });
    }

    public function markPayoutProcessing(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->isSettled()) {
            return $withdrawal;
        }

        $withdrawal->forceFill(['status' => Withdrawal::STATUS_PROCESSING])->save();

        return $withdrawal->fresh();
    }

    public function markPayoutSucceeded(Withdrawal $withdrawal, ?string $providerReference = null): Withdrawal
    {
        if ($withdrawal->status === Withdrawal::STATUS_COMPLETED) {
            return $withdrawal;
        }

        if (in_array($withdrawal->status, [Withdrawal::STATUS_FAILED, Withdrawal::STATUS_CANCELLED, Withdrawal::STATUS_EXPIRED], true)) {
            return $withdrawal;
        }

        $withdrawal->forceFill([
            'status' => Withdrawal::STATUS_COMPLETED,
            'external_reference' => $providerReference ?: $withdrawal->external_reference,
            'processed_at' => now(),
            'confirmed_at' => $withdrawal->confirmed_at ?? now(),
            'otp_hash' => null,
            'otp_expires_at' => null,
        ])->save();

        $this->auditLogger->log(
            'withdrawal_completed',
            null,
            $withdrawal->company_id,
            Withdrawal::class,
            $withdrawal->id,
            newValues: ['provider_reference' => $withdrawal->external_reference],
        );

        return $withdrawal->fresh();
    }

    public function markPayoutFailed(Withdrawal $withdrawal, string $reason): Withdrawal
    {
        if ($withdrawal->isSettled()) {
            return $withdrawal;
        }

        return DB::transaction(function () use ($withdrawal, $reason) {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->isSettled()) {
                return $withdrawal;
            }

            $this->refundWallet($withdrawal, 'Withdrawal refund '.$withdrawal->reference);

            $withdrawal->forceFill([
                'status' => Withdrawal::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => Str::limit($reason, 1000, ''),
                'otp_hash' => null,
                'otp_expires_at' => null,
            ])->save();

            $this->auditLogger->log(
                'withdrawal_failed',
                null,
                $withdrawal->company_id,
                Withdrawal::class,
                $withdrawal->id,
                newValues: ['reason' => $reason],
            );

            return $withdrawal->fresh();
        });
    }

    private function failAndRefund(Withdrawal $withdrawal, string $reason): void
    {
        $this->markPayoutFailed($withdrawal, $reason);
    }

    /**
     * Ensure the gross amount was taken out of the wallet exactly once. Normal
     * requests create this debit immediately as a hold; legacy/manual rows may
     * not have one yet.
     */
    private function debitWalletIfNotDebited(Withdrawal $withdrawal, string $description): void
    {
        $alreadyDebited = WalletTransaction::query()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'debit')
            ->exists();

        if ($alreadyDebited) {
            return;
        }

        $wallet = Wallet::query()->whereKey($withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
        $before = (float) $wallet->balance;

        if ($before < (float) $withdrawal->amount) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Insufficient wallet balance.'],
            ]);
        }

        $wallet->forceFill(['balance' => $before - (float) $withdrawal->amount])->save();

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $withdrawal->amount,
            'balance_before' => $before,
            'balance_after' => $wallet->balance,
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'description' => $description,
        ]);
    }

    /**
     * Credit the gross amount back to the wallet when the money was debited and
     * has not already been refunded.
     */
    private function refundWallet(Withdrawal $withdrawal, string $description): void
    {
        $alreadyRefunded = WalletTransaction::query()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'credit')
            ->exists();

        if ($alreadyRefunded) {
            return;
        }

        $wallet = Wallet::query()->whereKey($withdrawal->wallet_id)->lockForUpdate()->firstOrFail();
        $before = (float) $wallet->balance;
        $wallet->forceFill(['balance' => $before + (float) $withdrawal->amount])->save();

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $withdrawal->amount,
            'balance_before' => $before,
            'balance_after' => $wallet->balance,
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'description' => $description,
        ]);
    }

    private function resolvePayoutProvider(?string $slug): PaymentProvider
    {
        $provider = $slug !== null && $slug !== ''
            ? PaymentProvider::findBySlugOrFail($slug)
            : $this->providerManager->defaultForPayouts();

        if (! $provider->is_active || ! $provider->supports_payouts) {
            throw ValidationException::withMessages([
                'payment_provider_slug' => ['This payment provider does not support payouts.'],
            ]);
        }

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePayoutAccountForPreview(Company $company, array $data): ?PayoutAccount
    {
        if (isset($data['payout_account_id']) && $data['payout_account_id'] !== null && $data['payout_account_id'] !== '') {
            return PayoutAccount::query()
                ->where('company_id', $company->id)
                ->findOrFail((int) $data['payout_account_id']);
        }

        $phone = (string) ($data['destination_phone'] ?? $data['phone'] ?? '');

        if ($phone === '') {
            return null;
        }

        return PayoutAccount::query()
            ->where('company_id', $company->id)
            ->where('phone', $this->feeService->normalizePhone($phone))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePayoutAccount(Company $company, array $data): PayoutAccount
    {
        if (isset($data['payout_account_id']) && $data['payout_account_id'] !== null && $data['payout_account_id'] !== '') {
            return PayoutAccount::query()
                ->where('company_id', $company->id)
                ->findOrFail((int) $data['payout_account_id']);
        }

        $phone = $this->feeService->normalizePhone((string) ($data['destination_phone'] ?? $data['phone'] ?? ''));

        if ($phone === '') {
            throw ValidationException::withMessages([
                'destination_phone' => ['A payout mobile number is required.'],
            ]);
        }

        $name = trim((string) ($data['destination_name'] ?? $data['name'] ?? ''));
        $network = $this->feeService->detectNetwork($phone) ?: ($data['provider'] ?? null);

        return DB::transaction(function () use ($company, $phone, $name, $network) {
            $account = PayoutAccount::query()
                ->where('company_id', $company->id)
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            $isFirst = ! PayoutAccount::query()->where('company_id', $company->id)->exists();

            if ($account) {
                $account->forceFill([
                    'name' => $name !== '' ? $name : $account->name,
                    'network' => $network ?: $account->network,
                ])->save();

                return $account;
            }

            return PayoutAccount::query()->create([
                'company_id' => $company->id,
                'name' => $name !== '' ? $name : 'Payout account',
                'phone' => $phone,
                'network' => $network,
                'is_default' => $isFirst,
            ]);
        });
    }

    private function ownedWithdrawal(Company $company, Withdrawal $withdrawal): Withdrawal
    {
        return Withdrawal::query()
            ->whereKey($withdrawal->id)
            ->where('company_id', $company->id)
            ->firstOrFail();
    }

    private function sendOtp(Withdrawal $withdrawal, User $actor, string $code): void
    {
        $channel = $this->resolveOtpChannel($actor);

        if ($channel === 'sms') {
            $expires = $withdrawal->otp_expires_at?->toDateTimeString() ?? '';

            $message = 'WaiFai withdrawal code: '.$code
                .'. Net '.number_format((float) $withdrawal->net_amount, 2).' '.$withdrawal->currency
                .' to '.$withdrawal->destination_phone
                .'. Expires '.$expires.'.';

            if (app(SmsService::class)->sendOtp($actor, (string) $actor->phone, $message)) {
                $this->recordOtpChannel($withdrawal, 'sms');

                return;
            }

            Log::warning('withdrawal_otp_sms_failed_fallback_email', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $actor->id,
            ]);
        }

        try {
            $actor->notify(new WithdrawalOtpNotification(
                code: $code,
                withdrawal: $withdrawal,
                expiresAt: (string) $withdrawal->otp_expires_at?->toDateTimeString(),
            ));

            $this->recordOtpChannel($withdrawal, 'email');
        } catch (\Throwable $e) {
            Log::warning('withdrawal_otp_send_failed', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveOtpChannel(User $actor): string
    {
        return $actor->otp_channel === 'sms' && filled($actor->phone)
            ? 'sms'
            : 'email';
    }

    private function recordOtpChannel(Withdrawal $withdrawal, string $channel): void
    {
        $metadata = $withdrawal->metadata ?? [];
        $metadata['otp_channel'] = $channel;

        $withdrawal->forceFill(['metadata' => $metadata])->save();
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashOtp(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function otpExpiresAt(): Carbon
    {
        return now()->addMinutes(max(1, (int) config('withdrawals.otp_ttl_minutes', 10)));
    }
}
