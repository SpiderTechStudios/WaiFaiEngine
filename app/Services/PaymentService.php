<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\PaymentTransaction;
use App\Models\PlatformPayment;
use App\Models\RevenueRecord;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\NetworkDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Company $company, array $data, User $actor): PaymentTransaction
    {
        $plan = InternetPlan::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['internet_plan_id']);

        $amount = $data['amount'] ?? $plan->price;
        $currency = $data['currency'] ?? 'TZS';
        $status = $data['status'] ?? 'paid';

        return DB::transaction(function () use ($company, $plan, $amount, $currency, $status, $data, $actor) {
            $customer = $this->findOrCreateCustomer($company, $data);

            $payment = PaymentTransaction::query()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'reference' => 'PAY-'.strtoupper(Str::random(10)),
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $this->resolveNetworkPaymentMethod($data),
                'status' => $status,
                'initiated_at' => now(),
                'paid_at' => $status === 'paid' ? now() : null,
                'metadata' => [
                    'recorded_by' => $actor->id,
                ],
            ]);

            if ($status === 'paid') {
                $this->recognizePayment($company, $payment);
            }

            $this->auditLogger->log('payment_created', $actor, $company->id, PaymentTransaction::class, $payment->id);

            return $payment->load(['customer', 'internetPlan']);
        });
    }

    /**
     * Captive portal checkout: create a company PaymentTransaction, then push USSD
     * through the platform default payment provider (PlatformPayment + initiateCollection).
     *
     * @param  array<string, mixed>  $data
     */
    public function createForPortal(Company $company, array $data): PaymentTransaction
    {
        $plan = $this->resolveActivePlan($company, (int) $data['internet_plan_id']);

        $payment = DB::transaction(function () use ($company, $plan, $data) {
            $customer = $this->findOrCreateCustomer($company, $data);

            $payment = PaymentTransaction::query()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'reference' => 'PAY-'.strtoupper(Str::random(10)),
                'amount' => $plan->price,
                'currency' => config('platform.currency', 'TZS'),
                'payment_method' => $this->resolveNetworkPaymentMethod($data),
                'status' => 'pending',
                'initiated_at' => now(),
                'paid_at' => null,
                'metadata' => array_filter([
                    'source' => 'portal',
                    'captive_session' => $data['captive_session'] ?? null,
                    'network' => NetworkDetector::detect($data['customer_phone'] ?? null),
                ], fn ($value) => $value !== null && $value !== ''),
            ]);

            $this->auditLogger->log('payment_created', null, $company->id, PaymentTransaction::class, $payment->id);

            return $payment->load(['customer', 'internetPlan']);
        });

        try {
            $platformPayment = app(PlatformPaymentService::class)->startHotspotPortalPayment($payment, $data);
        } catch (\Throwable $e) {
            $payment->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'provider_error' => $e->getMessage(),
                ]),
            ])->save();

            throw $e;
        }

        $metadata = $payment->metadata ?? [];
        $metadata['platform_payment_id'] = $platformPayment->id;
        $metadata['platform_payment_reference'] = $platformPayment->reference;
        $metadata['provider'] = $platformPayment->provider_slug;
        $metadata['provider_charge'] = data_get($platformPayment->metadata, 'provider_charge');

        $payment->forceFill([
            'external_reference' => $platformPayment->external_reference,
            'metadata' => $metadata,
            'status' => $platformPayment->status === PlatformPayment::STATUS_PAID ? 'paid' : $payment->status,
            'paid_at' => $platformPayment->status === PlatformPayment::STATUS_PAID ? ($payment->paid_at ?: now()) : $payment->paid_at,
        ])->save();

        if ($platformPayment->status === PlatformPayment::STATUS_PAID && $payment->fresh()->status !== 'paid') {
            $this->markPortalPaid($payment->id, $platformPayment);
        }

        return $payment->fresh(['customer', 'internetPlan']);
    }

    /**
     * Mark a portal PaymentTransaction paid after the linked PlatformPayment succeeds.
     */
    public function markPortalPaid(int $paymentTransactionId, ?PlatformPayment $platformPayment = null): PaymentTransaction
    {
        return DB::transaction(function () use ($paymentTransactionId, $platformPayment) {
            $payment = PaymentTransaction::query()
                ->whereKey($paymentTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'paid') {
                return $payment->load(['customer', 'internetPlan']);
            }

            $metadata = $payment->metadata ?? [];
            if ($platformPayment) {
                $metadata['platform_payment_id'] = $platformPayment->id;
                $metadata['platform_payment_reference'] = $platformPayment->reference;
                $metadata['provider'] = $platformPayment->provider_slug;
                $metadata['provider_charge'] = data_get($platformPayment->metadata, 'provider_charge');
            }

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'failed_at' => null,
                'external_reference' => $platformPayment?->external_reference ?: $payment->external_reference,
                'metadata' => $metadata,
            ])->save();

            $company = Company::query()->whereKey($payment->company_id)->firstOrFail();
            $this->recognizePayment($company, $payment);

            $this->auditLogger->log(
                'portal_payment_paid',
                null,
                $payment->company_id,
                PaymentTransaction::class,
                $payment->id,
            );

            $this->authorizeCaptiveIfPresent($payment->fresh());

            return $payment->fresh(['customer', 'internetPlan']);
        });
    }

    /**
     * On poll: reconcile linked platform payment if still pending (e.g. PalmPesa order-status).
     * Provider status checks are intentionally spaced out — captive UI polls every few
     * seconds and a shared API token must not hammer the provider on every request.
     */
    public function refreshPortalPaymentStatus(PaymentTransaction $payment): PaymentTransaction
    {
        if ($payment->status !== 'pending') {
            return $payment->loadMissing(['customer', 'internetPlan']);
        }

        $platformPaymentId = (int) data_get($payment->metadata, 'platform_payment_id');
        if ($platformPaymentId <= 0) {
            return $payment->loadMissing(['customer', 'internetPlan']);
        }

        $platformPayment = PlatformPayment::query()->whereKey($platformPaymentId)->first();
        if (! $platformPayment) {
            return $payment->loadMissing(['customer', 'internetPlan']);
        }

        if ($platformPayment->status === PlatformPayment::STATUS_PAID) {
            return $this->markPortalPaid($payment->id, $platformPayment);
        }

        if ($platformPayment->status === PlatformPayment::STATUS_PENDING) {
            if (! $this->shouldReconcilePortalPaymentNow($payment)) {
                return $payment->loadMissing(['customer', 'internetPlan']);
            }

            try {
                $platformPayment = app(PlatformPaymentService::class)->reconcileProviderPayment($platformPayment);
                $this->rememberPortalReconcileAttempt($payment);
            } catch (\Throwable $e) {
                $this->rememberPortalReconcileAttempt($payment);
                Log::debug('Portal payment reconcile skipped', [
                    'payment_transaction_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($platformPayment->status === PlatformPayment::STATUS_PAID) {
                return $this->markPortalPaid($payment->id, $platformPayment);
            }

            if ($platformPayment->status === PlatformPayment::STATUS_FAILED) {
                $payment->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                ])->save();
            }
        }

        return $payment->fresh(['customer', 'internetPlan']);
    }

    private function shouldReconcilePortalPaymentNow(PaymentTransaction $payment): bool
    {
        $seconds = max(5, (int) config('services.palmpesa.portal_reconcile_seconds', 20));
        $last = data_get($payment->metadata, 'last_provider_reconcile_at');

        if (! is_string($last) || $last === '') {
            return true;
        }

        try {
            return Carbon::parse($last)->lteOrEqualTo(now()->subSeconds($seconds));
        } catch (\Throwable) {
            return true;
        }
    }

    private function rememberPortalReconcileAttempt(PaymentTransaction $payment): void
    {
        $metadata = $payment->metadata ?? [];
        $metadata['last_provider_reconcile_at'] = now()->toIso8601String();
        $payment->forceFill(['metadata' => $metadata])->save();
    }

    private function authorizeCaptiveIfPresent(PaymentTransaction $payment): void
    {
        $token = (string) data_get($payment->metadata, 'captive_session', '');
        if ($token === '') {
            return;
        }

        try {
            $captiveService = app(CaptiveSessionService::class);
            $captive = $captiveService->findByToken($token);

            if (! $captive || (int) $captive->company_id !== (int) $payment->company_id) {
                return;
            }

            if ($captive->isAuthenticated()) {
                return;
            }

            $captiveService->authenticate($captive, [
                'payment_transaction_id' => $payment->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Portal captive auto-authorize failed', [
                'payment_transaction_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveActivePlan(Company $company, int $planId): InternetPlan
    {
        $plan = InternetPlan::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereKey($planId)
            ->first();

        if ($plan) {
            return $plan;
        }

        $deleted = InternetPlan::onlyTrashed()
            ->where('company_id', $company->id)
            ->whereKey($planId)
            ->exists();

        throw ValidationException::withMessages([
            'internet_plan_id' => [
                $deleted
                    ? 'This package has been deleted. Choose an active package.'
                    : 'Package not found or is not available.',
            ],
        ]);
    }

    /**
     * Prefer MSISDN network wallet over a client-supplied "mpesa" label.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveNetworkPaymentMethod(array $data): string
    {
        $requested = (string) ($data['payment_method'] ?? '');

        if ($requested === 'voucher') {
            return 'voucher';
        }

        $detected = NetworkDetector::detect($data['customer_phone'] ?? null);
        if ($detected !== null) {
            return $detected;
        }

        return $requested !== '' ? $requested : 'mobile_money';
    }

    private function findOrCreateCustomer(Company $company, array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->where('company_id', $company->id)->findOrFail($data['customer_id']);
        }

        $query = Customer::query()->where('company_id', $company->id);
        if (! empty($data['customer_phone'])) {
            $existing = (clone $query)->where('phone', $data['customer_phone'])->first();
            if ($existing) {
                if (! empty($data['customer_name']) && $existing->name !== $data['customer_name']) {
                    $existing->forceFill(['name' => $data['customer_name']])->save();
                }

                return $existing;
            }
        }

        return Customer::query()->create([
            'company_id' => $company->id,
            'name' => $data['customer_name'] ?? 'Walk-in customer',
            'phone' => $data['customer_phone'] ?? null,
            'email' => $data['customer_email'] ?? null,
            'status' => 'active',
        ]);
    }

    private function recognizePayment(Company $company, PaymentTransaction $payment): void
    {
        $alreadyRecognized = RevenueRecord::query()
            ->where('payment_transaction_id', $payment->id)
            ->exists();

        if ($alreadyRecognized) {
            return;
        }

        $wallet = Wallet::query()->firstOrCreate(
            ['company_id' => $company->id, 'currency' => $payment->currency],
            ['balance' => 0, 'status' => 'active'],
        );

        $before = $wallet->balance;
        $wallet->forceFill(['balance' => $wallet->balance + $payment->amount])->save();

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $payment->amount,
            'balance_before' => $before,
            'balance_after' => $wallet->balance,
            'reference_type' => PaymentTransaction::class,
            'reference_id' => $payment->id,
            'description' => 'Payment '.$payment->reference,
        ]);

        RevenueRecord::query()->create([
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'source' => $payment->payment_method === 'voucher' ? 'voucher' : 'mobile_money',
            'payment_transaction_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'recognized',
            'recognized_at' => now(),
            'description' => 'Payment '.$payment->reference,
        ]);
    }
}
