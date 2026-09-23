<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $user = null;

        $enrollment = DB::transaction(function () use ($payment, &$user) {
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

            // Reservation TTL only blocks unpaid retries. Once the provider has
            // confirmed payment, the account must be created even if the signup
            // intent row already flipped to expired (mobile-money is async).
            if ($enrollment->status === Enrollment::STATUS_EXPIRED || $enrollment->expires_at->isPast()) {
                if (! $this->withinPaymentGrace($enrollment)) {
                    $metadata = $payment->metadata ?? [];
                    $metadata['completed_after_grace_window'] = true;
                    $payment->forceFill(['metadata' => $metadata])->save();

                    Log::warning('enrollment_completed_after_grace_window', [
                        'payment_id' => $payment->id,
                        'enrollment_id' => $enrollment->id,
                        'reference' => $enrollment->reference,
                        'expires_at' => optional($enrollment->expires_at)?->toIso8601String(),
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
            // Already bcrypt from enrollment create; Hash::isHashed keeps the cast
            // from re-hashing so the registrant can still log in.
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

        // Verification email is sent after the account is committed. A mail
        // failure must never roll back user/company creation (which would leave
        // a paid enrollment stuck without an account).
        if ($user instanceof User) {
            try {
                event(new Registered($user));
            } catch (\Throwable $e) {
                Log::warning('enrollment_verification_email_failed', [
                    'user_id' => $user->id,
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enrollment;
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
