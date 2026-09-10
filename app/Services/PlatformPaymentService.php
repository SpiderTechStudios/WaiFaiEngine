<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Enrollment;
use App\Models\InstallationRequest;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Payments\PaymentProviderManager;
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
        private PaymentProviderManager $providerManager,
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

        return $this->createPendingPayment([
            'type' => PlatformPayment::TYPE_PLATFORM_SUBSCRIPTION,
            'purpose' => PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION,
            'signup_intent_id' => $enrollment->id,
            'reference_prefix' => 'PAY',
            'amount' => $amount,
            'currency' => $enrollment->currency,
            'payment_method' => $data['payment_method'] ?? 'mobile_money',
            'phone' => $enrollment->payment_phone,
            'line_items' => [
                [
                    'code' => 'platform_subscription',
                    'label' => 'WaiFai Monthly Platform Subscription',
                    'amount' => (float) $enrollment->subscription_fee,
                ],
            ],
            'metadata' => [
                'source' => 'enrollment',
                'payment_purpose' => PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION,
                'enrollment_reference' => $enrollment->reference,
                'customer_email' => $enrollment->email,
                'customer_name' => trim($enrollment->first_name.' '.$enrollment->last_name),
            ],
            'existing_paid_query' => fn ($q) => $q->where('signup_intent_id', $enrollment->id),
            'initiate' => true,
        ]);
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
            'purpose' => PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL,
            'company_id' => $company->id,
            'reference_prefix' => 'PAY',
            'amount' => $amount,
            'currency' => PlatformPricing::currency(),
            'payment_method' => $data['payment_method'] ?? 'mobile_money',
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
                'customer_email' => $company->email,
                'customer_name' => $company->name,
            ],
            'existing_paid_query' => null,
            'initiate' => true,
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
            'purpose' => PlatformPayment::PURPOSE_INSTALLATION_REQUEST,
            'company_id' => $request->company_id,
            'installation_request_id' => $request->id,
            'reference_prefix' => 'PAY',
            'amount' => $amount,
            'currency' => $request->currency,
            'payment_method' => $data['payment_method'] ?? 'mobile_money',
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
            'initiate' => true,
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
                'processed_at' => now(),
                'failed_at' => null,
                'cancelled_at' => null,
            ])->save();

            $this->dispatchPurposeHandler($payment);

            $this->auditLogger->log(
                'platform_payment_paid',
                null,
                $payment->company_id,
                PlatformPayment::class,
                $payment->id,
                newValues: ['purpose' => $payment->resolvePurpose()],
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
                'processed_at' => now(),
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
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|array|null>  $headers
     */
    public function handleProviderWebhook(string $providerSlug, array $payload, array $headers = [], ?string $rawBody = null): PlatformPayment
    {
        $provider = PaymentProvider::findBySlugOrFail($providerSlug);
        $driver = $this->providerManager->driverFor($provider);

        if (! $driver->validateWebhook($headers, $payload, $rawBody)) {
            abort(401, 'Invalid payment provider webhook signature.');
        }

        $parsed = $driver->parseWebhook($payload);
        $reference = (string) ($parsed->txRef ?: ($payload['reference'] ?? $payload['transaction_reference'] ?? ''));

        if ($reference === '') {
            throw ValidationException::withMessages([
                'reference' => ['Payment reference is required.'],
            ]);
        }

        $payment = $this->findByReferenceOrFail($reference);

        if ($payment->provider_slug && $payment->provider_slug !== $providerSlug) {
            throw ValidationException::withMessages([
                'provider' => ['Callback provider does not match the payment intent provider.'],
            ]);
        }

        // Idempotent: already processed successfully.
        if ($payment->status === PlatformPayment::STATUS_PAID) {
            return $payment;
        }

        if ($parsed->amount !== null && (int) round($parsed->amount) !== (int) round((float) $payment->amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Callback amount does not match the payment intent.'],
            ]);
        }

        if ($parsed->currency !== null && strtoupper($parsed->currency) !== strtoupper((string) $payment->currency)) {
            throw ValidationException::withMessages([
                'currency' => ['Callback currency does not match the payment intent.'],
            ]);
        }

        // Prefer live verification when secrets exist.
        try {
            $verified = $driver->verifyTransaction($payment);
            if ($verified->isSuccessful()) {
                $parsed = $verified;
            } elseif ($verified->isFailed()) {
                $parsed = $verified;
            }
        } catch (\Throwable) {
            // Keep webhook parse result if verify is unavailable.
        }

        if ($parsed->providerReference) {
            $payment->forceFill([
                'external_reference' => $parsed->providerReference,
                'provider_event_id' => $parsed->providerReference,
            ])->save();
        }

        if ($parsed->isSuccessful()) {
            return $this->markPaid($payment->fresh());
        }

        if ($parsed->isFailed()) {
            return $this->markFailed($payment->fresh(), 'Provider reported failure');
        }

        return $payment->fresh();
    }

    /**
     * @deprecated Prefer handleProviderWebhook with provider slug.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleProviderCallback(array $payload): PlatformPayment
    {
        $slug = (string) ($payload['provider'] ?? PaymentProvider::SLUG_STUB);

        return $this->handleProviderWebhook($slug, $payload, [
            'X-Platform-Payment-Secret' => (string) request()->header('X-Platform-Payment-Secret'),
        ]);
    }

    private function dispatchPurposeHandler(PlatformPayment $payment): void
    {
        $purpose = $payment->resolvePurpose();

        if ($purpose === PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL && $payment->company_id) {
            $company = Company::query()->whereKey($payment->company_id)->lockForUpdate()->first();
            if ($company) {
                $this->subscriptionService->extendPeriod($company);
            }
        }

        if ($purpose === PlatformPayment::PURPOSE_INSTALLATION_REQUEST && $payment->installation_request_id) {
            $request = InstallationRequest::query()
                ->whereKey($payment->installation_request_id)
                ->lockForUpdate()
                ->first();

            if ($request) {
                $this->installationRequestService->markPaid($request);
            }
        }

        if (
            in_array($purpose, [
                PlatformPayment::PURPOSE_DEVICE_PURCHASE,
                PlatformPayment::PURPOSE_MARKETPLACE_ORDER,
            ], true)
            && $payment->order_id
        ) {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            if ($order) {
                app(MarketplaceOrderService::class)->markPaid($order);
            }
        }

        // platform_subscription is completed after commit via EnrollmentCompletionService
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function startDevicePurchasePayment(Order $order, array $data = []): PlatformPayment
    {
        $order->loadMissing('items');

        return $this->createPendingPayment([
            'type' => PlatformPayment::TYPE_DEVICE_PURCHASE,
            'purpose' => PlatformPayment::PURPOSE_DEVICE_PURCHASE,
            'order_id' => $order->id,
            'company_id' => $order->company_id,
            'reference_prefix' => 'DEV',
            'amount' => (float) $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => $data['payment_method'] ?? 'mobile_money',
            'phone' => $data['phone'] ?? $order->phone,
            'line_items' => $order->items->map(fn ($item) => [
                'code' => 'device_purchase',
                'device_id' => $item->device_id,
                'label' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'amount' => (float) $item->line_total,
            ])->all(),
            'metadata' => [
                'source' => 'marketplace',
                'payment_purpose' => PlatformPayment::PURPOSE_DEVICE_PURCHASE,
                'order_reference' => $order->reference,
            ],
            'existing_paid_query' => fn ($query) => $query->where('order_id', $order->id),
            'initiate' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createPendingPayment(array $payload): PlatformPayment
    {
        $provider = $this->providerManager->defaultForPayments();

        $payment = DB::transaction(function () use ($payload, $provider) {
            if ($payload['existing_paid_query']) {
                $query = PlatformPayment::query()->where('status', PlatformPayment::STATUS_PAID)->lockForUpdate();
                ($payload['existing_paid_query'])($query);
                $existingPaid = $query->first();
                if ($existingPaid) {
                    return $existingPaid;
                }
            }

            $payment = PlatformPayment::query()->create([
                'payment_provider_id' => $provider->id,
                'provider_slug' => $provider->slug,
                'type' => $payload['type'],
                'purpose' => $payload['purpose'] ?? $payload['type'],
                'direction' => PlatformPayment::DIRECTION_COLLECTION,
                'signup_intent_id' => $payload['signup_intent_id'] ?? null,
                'installation_request_id' => $payload['installation_request_id'] ?? null,
                'order_id' => $payload['order_id'] ?? null,
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

            $this->auditLogger->log(
                'platform_payment_started',
                null,
                $payment->company_id,
                PlatformPayment::class,
                $payment->id,
                newValues: [
                    'type' => $payment->type,
                    'purpose' => $payment->resolvePurpose(),
                    'provider' => $provider->slug,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                ],
            );

            return $payment;
        });

        if (($payload['initiate'] ?? false) && $payment->status === PlatformPayment::STATUS_PENDING) {
            $driver = $this->providerManager->driverFor($provider);
            $result = $driver->initiateCollection($payment);

            $metadata = $payment->metadata ?? [];
            $metadata['provider_charge'] = [
                'accepted' => $result->accepted,
                'message' => $result->message,
                'pushed_at' => now()->toIso8601String(),
                'raw' => $result->raw,
            ];

            $payment->forceFill([
                'external_reference' => $result->providerReference,
                'metadata' => $metadata,
            ])->save();
        }

        if (config('platform.payment_auto_paid') && $payment->status === PlatformPayment::STATUS_PENDING) {
            return $this->markPaid($payment->fresh());
        }

        return $payment->fresh();
    }
}
