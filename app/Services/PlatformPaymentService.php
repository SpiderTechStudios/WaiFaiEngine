<?php

namespace App\Services;

use App\Models\PlatformPayment;
use App\Models\SignupIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformPaymentService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private SignupIntentService $signupIntentService,
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

        $amount = (float) $data['amount'];
        if ((int) round($amount) !== (int) round((float) $intent->total_amount)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must match the signup total.'],
            ]);
        }

        $lineItems = $data['line_items'] ?? [
            ['code' => 'installation', 'amount' => (float) $intent->installation_fee],
            ['code' => 'subscription', 'amount' => (float) $intent->subscription_fee],
        ];

        $this->assertLineItems($intent, $lineItems);

        return DB::transaction(function () use ($intent, $data, $amount, $lineItems) {
            $existingPaid = PlatformPayment::query()
                ->where('signup_intent_id', $intent->id)
                ->where('status', PlatformPayment::STATUS_PAID)
                ->lockForUpdate()
                ->first();

            if ($existingPaid) {
                return $existingPaid;
            }

            $payment = PlatformPayment::query()->create([
                'type' => PlatformPayment::TYPE_SIGNUP,
                'signup_intent_id' => $intent->id,
                'reference' => 'SUB-SIGNUP-'.strtoupper(Str::random(10)),
                'amount' => $amount,
                'currency' => $intent->currency,
                'payment_method' => $data['payment_method'] ?? 'mpesa',
                'phone' => $data['phone'] ?? $intent->phone,
                'status' => PlatformPayment::STATUS_PENDING,
                'line_items' => $lineItems,
                'metadata' => ['source' => 'signup'],
                'initiated_at' => now(),
            ]);

            if (config('platform.payment_auto_paid')) {
                $this->markPaid($payment);
                $payment->refresh();
            }

            $this->auditLogger->log(
                'platform_payment_started',
                null,
                null,
                PlatformPayment::class,
                $payment->id,
                newValues: [
                    'signup_intent_id' => $intent->id,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                ],
            );

            return $payment;
        });
    }

    public function findForIntentOrFail(SignupIntent $intent, int $paymentId): PlatformPayment
    {
        return PlatformPayment::query()
            ->where('signup_intent_id', $intent->id)
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

                if (
                    $intent
                    && $intent->status === SignupIntent::STATUS_PENDING_PAYMENT
                ) {
                    $intent->forceFill(['status' => SignupIntent::STATUS_PAID])->save();
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
     * @param  list<array<string, mixed>>  $lineItems
     */
    private function assertLineItems(SignupIntent $intent, array $lineItems): void
    {
        $byCode = [];
        foreach ($lineItems as $item) {
            $code = (string) ($item['code'] ?? '');
            $byCode[$code] = (float) ($item['amount'] ?? 0);
        }

        if (
            ! isset($byCode['installation'], $byCode['subscription'])
            || (int) round($byCode['installation']) !== (int) round((float) $intent->installation_fee)
            || (int) round($byCode['subscription']) !== (int) round((float) $intent->subscription_fee)
        ) {
            throw ValidationException::withMessages([
                'line_items' => ['Line items must match the signup installation and subscription fees.'],
            ]);
        }
    }
}
