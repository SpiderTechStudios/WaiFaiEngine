<?php

namespace App\Services;

use App\Models\PlatformPayment;
use App\Models\SignupIntent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SignupCompletionService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AuthService $authService,
        private CompanyService $companyService,
        private SignupIntentService $signupIntentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function complete(SignupIntent $intent): array
    {
        return DB::transaction(function () use ($intent) {
            $intent = SignupIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $this->signupIntentService->syncExpiry($intent);
            $intent->refresh();

            if ($intent->user_id || $intent->status === SignupIntent::STATUS_COMPLETED) {
                $user = User::query()->findOrFail($intent->user_id);
                $token = $user->createToken('auth')->plainTextToken;

                return $this->authService->sessionPayload($user->fresh(), $token);
            }

            if (
                $intent->status === SignupIntent::STATUS_EXPIRED
                || $intent->expires_at->isPast()
            ) {
                if ($intent->status !== SignupIntent::STATUS_EXPIRED) {
                    $intent->forceFill(['status' => SignupIntent::STATUS_EXPIRED])->save();
                }

                throw ValidationException::withMessages([
                    'intent' => ['This signup intent has expired.'],
                ]);
            }

            $payment = PlatformPayment::query()
                ->where('signup_intent_id', $intent->id)
                ->where('status', PlatformPayment::STATUS_PAID)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages([
                    'intent' => ['Signup payment is not paid yet.'],
                ]);
            }

            if (! in_array($intent->status, [
                SignupIntent::STATUS_PAID,
                SignupIntent::STATUS_PENDING_PAYMENT,
            ], true)) {
                throw ValidationException::withMessages([
                    'intent' => ['This signup intent cannot be completed.'],
                ]);
            }

            $this->signupIntentService->assertEmailAvailable($intent->email, $intent->id);
            if ($intent->portal_subdomain) {
                $this->signupIntentService->assertSubdomainAvailable($intent->portal_subdomain, $intent->id);
            }

            $user = new User([
                'first_name' => $intent->first_name,
                'last_name' => $intent->last_name,
                'email' => $intent->email,
                'phone' => $intent->phone,
                'status' => 'pending',
            ]);
            $user->password = $intent->password_hash;
            $user->save();

            $company = $this->companyService->create($user, [
                'name' => $intent->business_name,
                'email' => $intent->email,
                'phone' => $intent->phone,
                'address' => $intent->address,
                'subdomain' => $intent->portal_subdomain,
                'setup_type' => $intent->setup_type,
                'installation_paid_at' => $payment->paid_at ?? now(),
                'subscription_status' => 'active',
                'activated_at' => now(),
                'subscription_period_ends_at' => now()->addMonth(),
            ]);

            $payment->forceFill(['company_id' => $company->id])->save();

            $intent->forceFill([
                'status' => SignupIntent::STATUS_COMPLETED,
                'user_id' => $user->id,
                'company_id' => $company->id,
            ])->save();

            event(new Registered($user));

            $token = $user->createToken('auth')->plainTextToken;

            $this->auditLogger->log(
                'signup_completed',
                $user,
                $company->id,
                SignupIntent::class,
                null,
                newValues: [
                    'intent_id' => $intent->id,
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ],
            );

            return $this->authService->sessionPayload($user->fresh(), $token);
        });
    }
}
