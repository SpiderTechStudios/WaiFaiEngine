<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SignupIntent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignupIntentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SignupIntent
    {
        $pricing = $this->assertPricing(
            (string) $data['setup_type'],
            (float) $data['installation_fee'],
            (float) $data['subscription_fee'],
            (float) $data['total_amount'],
            (string) ($data['currency'] ?? config('platform.currency')),
        );

        $email = Str::lower(trim((string) $data['email']));
        $this->assertEmailAvailable($email);

        $subdomain = filled($data['portal_subdomain'] ?? null)
            ? Str::lower(trim((string) $data['portal_subdomain']))
            : null;

        if ($subdomain) {
            $this->assertSubdomainAvailable($subdomain);
        }

        $intent = SignupIntent::query()->create([
            'status' => SignupIntent::STATUS_PENDING_PAYMENT,
            'setup_type' => $pricing['setup_type'],
            'installation_fee' => $pricing['installation_fee'],
            'subscription_fee' => $pricing['subscription_fee'],
            'total_amount' => $pricing['total_amount'],
            'currency' => $pricing['currency'],
            'business_name' => $data['business_name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'],
            'address' => $data['address'],
            'portal_subdomain' => $subdomain,
            'password_hash' => Hash::make((string) $data['password']),
            'expires_at' => now()->addHours((int) config('platform.signup_intent_ttl_hours', 48)),
        ]);

        $this->auditLogger->log(
            'signup_intent_created',
            null,
            null,
            SignupIntent::class,
            null,
            newValues: [
                'intent_id' => $intent->id,
                'email' => $intent->email,
                'setup_type' => $intent->setup_type,
                'total_amount' => $intent->total_amount,
            ],
        );

        return $intent;
    }

    public function findActiveOrFail(string $intentId): SignupIntent
    {
        $intent = SignupIntent::query()->findOrFail($intentId);
        $this->syncExpiry($intent);

        return $intent->fresh();
    }

    public function syncExpiry(SignupIntent $intent): void
    {
        if (
            $intent->status === SignupIntent::STATUS_PENDING_PAYMENT
            && $intent->expires_at->isPast()
        ) {
            $intent->forceFill(['status' => SignupIntent::STATUS_EXPIRED])->save();
        }
    }

    /**
     * @return array{
     *     setup_type: string,
     *     installation_fee: float,
     *     subscription_fee: float,
     *     total_amount: float,
     *     currency: string
     * }
     */
    public function assertPricing(
        string $setupType,
        float $installationFee,
        float $subscriptionFee,
        float $totalAmount,
        string $currency,
    ): array {
        $allowedCurrency = (string) config('platform.currency', 'TZS');
        $expectedSubscription = (float) config('platform.subscription_monthly');
        $expectedInstallation = match ($setupType) {
            SignupIntent::SETUP_ASSISTED => (float) config('platform.installation.assisted'),
            SignupIntent::SETUP_SELF => (float) config('platform.installation.self'),
            default => null,
        };

        if ($expectedInstallation === null) {
            throw ValidationException::withMessages([
                'setup_type' => ['Setup type must be assisted or self.'],
            ]);
        }

        if (strtoupper($currency) !== strtoupper($allowedCurrency)) {
            throw ValidationException::withMessages([
                'currency' => ["Currency must be {$allowedCurrency}."],
            ]);
        }

        if ((int) round($installationFee) !== (int) round($expectedInstallation)) {
            throw ValidationException::withMessages([
                'installation_fee' => ["Installation fee must be {$expectedInstallation} for setup type {$setupType}."],
            ]);
        }

        if ((int) round($subscriptionFee) !== (int) round($expectedSubscription)) {
            throw ValidationException::withMessages([
                'subscription_fee' => ["Subscription fee must be {$expectedSubscription}."],
            ]);
        }

        $expectedTotal = $expectedInstallation + $expectedSubscription;
        if ((int) round($totalAmount) !== (int) round($expectedTotal)) {
            throw ValidationException::withMessages([
                'total_amount' => ["Total amount must be {$expectedTotal}."],
            ]);
        }

        return [
            'setup_type' => $setupType,
            'installation_fee' => $expectedInstallation,
            'subscription_fee' => $expectedSubscription,
            'total_amount' => $expectedTotal,
            'currency' => $allowedCurrency,
        ];
    }

    public function assertEmailAvailable(string $email, ?string $ignoreIntentId = null): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        $reserved = SignupIntent::query()
            ->where('email', $email)
            ->whereIn('status', SignupIntent::ACTIVE_RESERVATION_STATUSES)
            ->where('expires_at', '>', now())
            ->when($ignoreIntentId, fn ($q) => $q->where('id', '!=', $ignoreIntentId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'email' => ['This email already has a pending signup.'],
            ]);
        }
    }

    public function assertSubdomainAvailable(string $subdomain, ?string $ignoreIntentId = null): void
    {
        if (Company::query()->where('subdomain', $subdomain)->exists()) {
            throw ValidationException::withMessages([
                'portal_subdomain' => ['This portal subdomain is already taken.'],
            ]);
        }

        $reserved = SignupIntent::query()
            ->where('portal_subdomain', $subdomain)
            ->whereIn('status', SignupIntent::ACTIVE_RESERVATION_STATUSES)
            ->where('expires_at', '>', now())
            ->when($ignoreIntentId, fn ($q) => $q->where('id', '!=', $ignoreIntentId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'portal_subdomain' => ['This portal subdomain is reserved by another signup.'],
            ]);
        }
    }
}
