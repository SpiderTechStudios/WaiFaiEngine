<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Enrollment;
use App\Models\InstallationRequest;
use App\Models\PlatformPayment;
use App\Support\PlatformPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformPaymentService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private EnrollmentService $enrollmentService,
        private EnrollmentCompletionService $enrollmentCompletionService,
        private SubscriptionService $subscriptionService,
        private InstallationRequestService $installationRequestService,
        private PlatformPaymentGateway $paymentGateway,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function startEnrollmentPayment(Enrollment $enrollment, array $data = []): PlatformPayment
    {
        $this->enrollmentService->assertUsableForPayment($enrollment);
        $enrollment->refresh();

        if (! $enrollment->canRetryPayment() && $enrollment->payments()->exists()) {
            throw ValidationException::withMessages([
                'enrollment' => ['This enrollment cannot accept another payment attempt.'],
            ]);
        }

        $amount = (float) $enrollment->total_amount;

        if (array_key_exists('amount', $data) && (int) round((float) $data['amount']) !== (int) round($amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must match the monthly platform subscription fee.'],
            ]);
        }

        if (filled($data['payment_phone'] ?? null) || filled($data['phone'] ?? null)) {
            $phone = (string) ($data['payment_phone'] ?? $data['phone']);
            $enrollment->forceFill(['payment_phone' => $phone])->save();
        }

        $enrollment->payments()
            ->where('status', PlatformPayment::STATUS_PENDING)
            ->update([
                'status' => PlatformPayment::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

        $enrollment->forceFill(['status' => Enrollment::STATUS_PENDING_PAYMENT])->save();

        $lineItems = [
            [
                'code' => 'platform_subscription',
                'label' => 'WaiFai Monthly Platform Subscription',
                'amount' => (float) $enrollment->subscription_fee,
            ],
        ];

        $payment = $this->createPendingPayment([
            'type' => PlatformPayment::TYPE_PLATFORM_SUBSCRIPTION,
            'signup_intent_id' => $enrollment->id,
            'reference_prefix' => 'SUB-ENR',
            'amount' => $amount,
            'currency' => $enrollment->currency,
            'payment_method' => $data['payment_method'] ?? 'mpesa',
            'phone' => $enrollment->payment_phone,
            'line_items' => $lineItems,
            'metadata' => [
                'source' => 'enrollment',
                'payment_purpose' => PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION,
                'enrollment_reference' => $enrollment->reference,
            ],
            'existing_paid_query' => fn ($q) => $q->where('signup_intent_id', $enrollment->id),
            'push_ussd' => true,
        ]);

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function retryEnrollmentPayment(Enrollment $enrollment, array $data = []): PlatformPayment
    {
        $this->enrollmentService->assertUsableForPayment($enrollment);
        $enrollment->refresh();

        if (! $enrollment->canRetryPayment()) {
            throw ValidationException::withMessages([
                'enrollment' => ['No payment retries remain for this enrollment.'],
            ]);
        }

        if ($enrollment->payments()->where('status', PlatformPayment::STATUS_PAID)->exists()) {
            throw ValidationException::withMessages([
                'enrollment' => ['Payment has already succeeded for this enrollment.'],
            ]);
        }

        return $this->startEnrollmentPayment($enrollment, $data);
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
            'metadata' => [
                'source' => 'renewal',
                'payment_purpose' => PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL,
            ],
            'existing_paid_query' => null,
            'push_ussd' => true,
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
                'payment_purpose' => PlatformPayment::PURPOSE_INSTALLATION_REQUEST,
                'installation_request_id' => $request->id,
            ],
            'existing_paid_query' => fn ($q) => $q->where('installation_request_id', $request->id),
            'push_ussd' => true,
        ]);
    }

    public function findForEnrollmentOrFail(Enrollment $enrollment, int $paymentId): PlatformPayment
    {
        return PlatformPayment::query()
            ->where('signup_intent_id', $enrollment->id)
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

    public function findByReferenceOrFail(string $reference): PlatformPayment
    {
        return PlatformPayment::query()
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function markPaid(PlatformPayment $payment): PlatformPayment
    {
        $payment = DB::transaction(function () use ($payment) {
            $payment = PlatformPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PlatformPayment::STATUS_PAID) {
                return $payment;
            }

            if ($payment->status === PlatformPayment::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'payment' => ['Cancelled payments cannot be marked paid.'],
                ]);
            }

            $payment->forceFill([
                'status' => PlatformPayment::STATUS_PAID,
                'paid_at' => now(),
                'failed_at' => null,
                'cancelled_at' => null,
            ])->save();

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

        if ($payment->isEnrollmentSubscription() && $payment->signup_intent_id) {
            $this->enrollmentCompletionService->completeFromPayment($payment);
            $payment->refresh();
        }

        return $payment;
    }

    public function markFailed(PlatformPayment $payment, ?string $reason = null): PlatformPayment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment = PlatformPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (in_array($payment->status, [PlatformPayment::STATUS_PAID, PlatformPayment::STATUS_FAILED], true)) {
                return $payment;
            }

            $metadata = $payment->metadata ?? [];
            if ($reason) {
                $metadata['failure_reason'] = $reason;
            }

            $payment->forceFill([
                'status' => PlatformPayment::STATUS_FAILED,
                'failed_at' => now(),
                'metadata' => $metadata,
            ])->save();

            if ($payment->signup_intent_id) {
                $enrollment = Enrollment::query()
                    ->whereKey($payment->signup_intent_id)
                    ->lockForUpdate()
                    ->first();

                if ($enrollment && ! $enrollment->isCompleted()) {
                    $attempts = (int) $enrollment->failed_payment_attempts + 1;
                    $enrollment->forceFill([
                        'failed_payment_attempts' => $attempts,
                        'status' => Enrollment::STATUS_PAYMENT_FAILED,
                    ])->save();

                    if ($attempts >= $enrollment->maxFailedAttempts()) {
                        $this->enrollmentService->invalidateAfterFailedAttempts($enrollment->fresh());
                    }
                }
            }

            $this->auditLogger->log(
                'platform_payment_failed',
                null,
                $payment->company_id,
                PlatformPayment::class,
                $payment->id,
                newValues: ['reason' => $reason],
            );

            return $payment->fresh();
        });
    }

    /**
     * Trusted provider callback handler.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleProviderCallback(array $payload): PlatformPayment
    {
        $reference = (string) ($payload['reference'] ?? $payload['transaction_reference'] ?? '');
        if ($reference === '') {
            throw ValidationException::withMessages([
                'reference' => ['Payment reference is required.'],
            ]);
        }

        $payment = $this->findByReferenceOrFail($reference);

        $expectedAmount = (int) round((float) $payment->amount);
        if (isset($payload['amount']) && (int) round((float) $payload['amount']) !== $expectedAmount) {
            throw ValidationException::withMessages([
                'amount' => ['Callback amount does not match the payment.'],
            ]);
        }

        if (isset($payload['currency']) && strtoupper((string) $payload['currency']) !== strtoupper((string) $payment->currency)) {
            throw ValidationException::withMessages([
                'currency' => ['Callback currency does not match the payment.'],
            ]);
        }

        $status = strtolower((string) ($payload['status'] ?? ''));

        return match ($status) {
            'paid', 'success', 'successful', 'completed' => $this->markPaid($payment),
            'failed', 'failure', 'cancelled', 'canceled' => $this->markFailed(
                $payment,
                (string) ($payload['failure_reason'] ?? $payload['message'] ?? 'Provider reported failure'),
            ),
            default => throw ValidationException::withMessages([
                'status' => ['Unsupported payment status.'],
            ]),
        };
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

            if ($payload['push_ussd'] ?? false) {
                $this->paymentGateway->pushUssd($payment);
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

        if (config('platform.payment_auto_paid') && $payment->status === PlatformPayment::STATUS_PENDING) {
            return $this->markPaid($payment);
        }

        return $payment;
    }
}
