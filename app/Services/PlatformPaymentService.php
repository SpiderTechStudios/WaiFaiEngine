<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InstallationRequest;
use App\Models\PlatformPayment;
use App\Models\SignupIntent;
use App\Support\PlatformPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformPaymentService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private SignupIntentService $signupIntentService,
        private SubscriptionService $subscriptionService,
        private InstallationRequestService $installationRequestService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function startSignupPayment(SignupIntent $intent, array $data): PlatformPayment
    {
        $this->signupIntentService->syncExpiry($intent);
        $intent->refresh();

        if ($intent->status === SignupIntent::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'intent' => ['This signup has already been completed.'],
            ]);
        }

        if ($intent->isExpired() || $intent->status === SignupIntent::STATUS_EXPIRED) {
            throw ValidationException::withMessages([
                'intent' => ['This signup intent has expired.'],
            ]);
        }

        if (! in_array($intent->status, [
            SignupIntent::STATUS_PENDING_PAYMENT,
            SignupIntent::STATUS_PAID,
        ], true)) {
            throw ValidationException::withMessages([
                'intent' => ['This signup intent cannot accept payments.'],
            ]);
        }

        $amount = array_key_exists('amount', $data)
            ? (float) $data['amount']
            : (float) $intent->total_amount;

        if ((int) round($amount) !== (int) round((float) $intent->total_amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must match the first month subscription fee.'],
            ]);
        }

        $lineItems = [
            [
                'code' => 'subscription',
                'label' => 'First month subscription',
                'amount' => (float) $intent->subscription_fee,
            ],
        ];

        return $this->createPendingPayment([
            'type' => PlatformPayment::TYPE_SIGNUP,
            'signup_intent_id' => $intent->id,
            'reference_prefix' => 'SUB-SIGNUP',
            'amount' => $amount,
            'currency' => $intent->currency,
            'payment_method' => $data['payment_method'] ?? 'mpesa',
            'phone' => $data['phone'] ?? $intent->payment_phone,
            'line_items' => $lineItems,
            'metadata' => ['source' => 'signup'],
            'existing_paid_query' => fn ($q) => $q->where('signup_intent_id', $intent->id),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function startRenewalPayment(Company $company, array $data): PlatformPayment
    {
        $amount = PlatformPricing::subscriptionMonthly();

        if (array_key_exists('amount', $data) && (int) round((float) $data['amount']) !== (int) round($amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must match the monthly subscription fee.'],
            ]);
        }

        return $this->createPendingPayment([
            'type' => PlatformPayment::TYPE_SUBSCRIPTION_RENEWAL,
            'company_id' => $company->id,
            'reference_prefix' => 'SUB-RENEW',
            'amount' => $amount,
            'currency' => PlatformPricing::currency(),
            'payment_method' => $data['payment_method'] ?? 'mpesa',
            'phone' => $data['phone'] ?? $company->phone,
            'line_items' => [
                [
                    'code' => 'subscription',
                    'label' => 'Monthly subscription renewal',
                    'amount' => $amount,
                ],
            ],
            'metadata' => ['source' => 'renewal'],
            'existing_paid_query' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function startInstallationPayment(InstallationRequest $request, array $data): PlatformPayment
    {
        if ($request->payment_status === InstallationRequest::PAYMENT_PAID) {
            $existing = PlatformPayment::query()
                ->where('installation_request_id', $request->id)
                ->where('status', PlatformPayment::STATUS_PAID)
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $amount = (float) $request->total_amount;

        if (array_key_exists('amount', $data) && (int) round((float) $data['amount']) !== (int) round($amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must match the installation request total.'],
            ]);
        }

        return $this->createPendingPayment([
            'type' => PlatformPayment::TYPE_INSTALLATION,
            'company_id' => $request->company_id,
            'installation_request_id' => $request->id,
            'reference_prefix' => 'INS-PAY',
            'amount' => $amount,
            'currency' => $request->currency,
            'payment_method' => $data['payment_method'] ?? 'mpesa',
            'phone' => $data['phone'] ?? null,
            'line_items' => [
                [
                    'code' => 'installation',
                    'label' => $request->service_type,
                    'quantity' => $request->quantity,
                    'unit_price' => (float) $request->unit_price,
                    'amount' => $amount,
                ],
            ],
            'metadata' => [
                'source' => 'installation',
                'installation_request_id' => $request->id,
            ],
            'existing_paid_query' => fn ($q) => $q->where('installation_request_id', $request->id),
        ]);
    }

    public function findForIntentOrFail(SignupIntent $intent, int $paymentId): PlatformPayment
    {
        return PlatformPayment::query()
            ->where('signup_intent_id', $intent->id)
            ->whereKey($paymentId)
            ->firstOrFail();
    }

    public function findForCompanyOrFail(Company $company, int $paymentId): PlatformPayment
    {
        return PlatformPayment::query()
            ->where('company_id', $company->id)
            ->whereKey($paymentId)
            ->firstOrFail();
    }

    public function findForInstallationOrFail(InstallationRequest $request, int $paymentId): PlatformPayment
    {
        return PlatformPayment::query()
            ->where('installation_request_id', $request->id)
            ->whereKey($paymentId)
            ->firstOrFail();
    }

    public function markPaid(PlatformPayment $payment): PlatformPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = PlatformPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PlatformPayment::STATUS_PAID) {
                return $payment;
            }

            $payment->forceFill([
                'status' => PlatformPayment::STATUS_PAID,
                'paid_at' => now(),
                'failed_at' => null,
                'cancelled_at' => null,
            ])->save();

            if ($payment->signup_intent_id) {
                $intent = SignupIntent::query()
                    ->whereKey($payment->signup_intent_id)
                    ->lockForUpdate()
                    ->first();

                if ($intent && $intent->status === SignupIntent::STATUS_PENDING_PAYMENT) {
                    $intent->forceFill(['status' => SignupIntent::STATUS_PAID])->save();
                }
            }

            if ($payment->type === PlatformPayment::TYPE_SUBSCRIPTION_RENEWAL && $payment->company_id) {
                $company = Company::query()->whereKey($payment->company_id)->lockForUpdate()->first();
                if ($company) {
                    $this->subscriptionService->extendPeriod($company);
                }
            }

            if ($payment->type === PlatformPayment::TYPE_INSTALLATION && $payment->installation_request_id) {
                $request = InstallationRequest::query()
                    ->whereKey($payment->installation_request_id)
                    ->lockForUpdate()
                    ->first();

                if ($request) {
                    $this->installationRequestService->markPaid($request);
                }
            }

            $this->auditLogger->log(
                'platform_payment_paid',
                null,
                $payment->company_id,
                PlatformPayment::class,
                $payment->id,
            );

            return $payment->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createPendingPayment(array $payload): PlatformPayment
    {
        return DB::transaction(function () use ($payload) {
            if ($payload['existing_paid_query']) {
                $query = PlatformPayment::query()->where('status', PlatformPayment::STATUS_PAID)->lockForUpdate();
                ($payload['existing_paid_query'])($query);
                $existingPaid = $query->first();
                if ($existingPaid) {
                    return $existingPaid;
                }
            }

            $payment = PlatformPayment::query()->create([
                'type' => $payload['type'],
                'signup_intent_id' => $payload['signup_intent_id'] ?? null,
                'installation_request_id' => $payload['installation_request_id'] ?? null,
                'company_id' => $payload['company_id'] ?? null,
                'reference' => $payload['reference_prefix'].'-'.strtoupper(Str::random(10)),
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'payment_method' => $payload['payment_method'],
                'phone' => $payload['phone'],
                'status' => PlatformPayment::STATUS_PENDING,
                'line_items' => $payload['line_items'],
                'metadata' => $payload['metadata'],
                'initiated_at' => now(),
            ]);

            if (config('platform.payment_auto_paid')) {
                $this->markPaid($payment);
                $payment->refresh();
            }

            $this->auditLogger->log(
                'platform_payment_started',
                null,
                $payment->company_id,
                PlatformPayment::class,
                $payment->id,
                newValues: [
                    'type' => $payment->type,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                ],
            );

            return $payment;
        });
    }
}
