<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentCompletionService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private CompanyService $companyService,
        private EnrollmentService $enrollmentService,
    ) {}

    /**
     * Create user + company after trusted payment confirmation.
     * Idempotent: safe under webhook replay. Does not issue auth tokens.
     */
    public function completeFromPayment(PlatformPayment $payment): Enrollment
    {
        return DB::transaction(function () use ($payment) {
            $payment = PlatformPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->signup_intent_id) {
                throw ValidationException::withMessages([
                    'payment' => ['Payment is not linked to an enrollment.'],
                ]);
            }

            if ($payment->status !== PlatformPayment::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'payment' => ['Enrollment payment is not paid.'],
                ]);
            }

            if (! $payment->isEnrollmentSubscription()) {
                throw ValidationException::withMessages([
                    'payment' => ['Payment purpose is not a platform subscription enrollment.'],
                ]);
            }

            $enrollment = Enrollment::query()
                ->whereKey($payment->signup_intent_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($enrollment->isCompleted()) {
                return $enrollment;
            }

            if ($enrollment->status === Enrollment::STATUS_EXPIRED || $enrollment->expires_at->isPast()) {
                if (! $this->withinPaymentGrace($enrollment)) {
                    $metadata = $payment->metadata ?? [];
                    $metadata['requires_manual_review'] = true;
                    $metadata['manual_review_reason'] = 'confirmed_payment_after_grace_window';
                    $payment->forceFill(['metadata' => $metadata])->save();

                    throw ValidationException::withMessages([
                        'enrollment' => ['This enrollment has expired and cannot create an account.'],
                    ]);
                }

                if ($enrollment->status !== Enrollment::STATUS_EXPIRED) {
                    $enrollment->forceFill(['status' => Enrollment::STATUS_EXPIRED])->save();
                }
            }

            $this->enrollmentService->assertEmailAvailable($enrollment->email, $enrollment->id);
            $this->enrollmentService->assertPhoneAvailable($enrollment->phone, $enrollment->id);

            if ($enrollment->portal_subdomain) {
                $this->enrollmentService->assertDomainAvailable($enrollment->portal_subdomain, $enrollment->id);
            }

            $user = new User([
                'first_name' => $enrollment->first_name,
                'last_name' => $enrollment->last_name,
                'email' => $enrollment->email,
                'phone' => $enrollment->phone,
                'status' => 'pending',
            ]);
            $user->password = $enrollment->password_hash;
            $user->save();

            $company = $this->companyService->create($user, [
                'name' => $enrollment->business_name,
                'email' => $enrollment->email,
                'phone' => $enrollment->phone,
                'address' => $enrollment->address,
                'subdomain' => $enrollment->portal_subdomain,
                'subscription_status' => 'active',
                'activated_at' => now(),
                'subscription_period_ends_at' => now()->addMonth(),
            ]);

            $payment->forceFill(['company_id' => $company->id])->save();

            $enrollment->forceFill([
                'status' => Enrollment::STATUS_COMPLETED,
                'user_id' => $user->id,
                'company_id' => $company->id,
            ])->save();

            event(new Registered($user));

            $this->auditLogger->log(
                'enrollment_completed',
                $user,
                $company->id,
                Enrollment::class,
                null,
                newValues: [
                    'enrollment_id' => $enrollment->id,
                    'reference' => $enrollment->reference,
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ],
            );

            return $enrollment->fresh();
        });
    }

    /**
     * A provider-confirmed payment may still create the account for a while
     * after the reservation TTL lapsed (mobile-money approvals are async).
     */
    private function withinPaymentGrace(Enrollment $enrollment): bool
    {
        if (! $enrollment->expires_at) {
            return true;
        }

        $graceMinutes = max(0, (int) config('platform.enrollment_payment_grace_minutes', 60));

        return now()->lessThanOrEqualTo(
            $enrollment->expires_at->copy()->addMinutes($graceMinutes)
        );
    }
}
