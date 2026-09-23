<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\PlatformPricing;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnrollmentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Enrollment
    {
        $pricing = $this->resolvePricing();

        $email = Str::lower(trim((string) $data['email']));
        $this->assertEmailAvailable($email);
        $this->assertPhoneAvailable((string) $data['phone']);

        $domainName = $this->normalizeDomainName($data['domain_name'] ?? $data['portal_subdomain'] ?? null);

        if ($domainName) {
            $this->assertDomainAvailable($domainName);
        }

        $ttlMinutes = max(1, (int) config('platform.enrollment_ttl_minutes', 4));

        $enrollment = Enrollment::query()->create([
            'reference' => $this->generateReference(),
            'status' => Enrollment::STATUS_PENDING_PAYMENT,
            'failed_payment_attempts' => 0,
            'subscription_fee' => $pricing['subscription_fee'],
            'total_amount' => $pricing['total_amount'],
            'currency' => $pricing['currency'],
            'business_name' => $data['business_name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'],
            'payment_phone' => $data['payment_phone'],
            'address' => $data['address'],
            'portal_subdomain' => $domainName,
            'password_hash' => Hash::make((string) $data['password']),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        $this->auditLogger->log(
            'enrollment_created',
            null,
            null,
            Enrollment::class,
            null,
            newValues: [
                'enrollment_id' => $enrollment->id,
                'reference' => $enrollment->reference,
                'email' => $enrollment->email,
                'total_amount' => $enrollment->total_amount,
            ],
        );

        return $enrollment;
    }

    public function findByReferenceOrFail(string $reference): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('reference', $reference)
            ->firstOrFail();

        $this->syncExpiry($enrollment);

        return $enrollment->fresh();
    }

    public function syncExpiry(Enrollment $enrollment): void
    {
        if ($enrollment->isCompleted()) {
            return;
        }

        if (
            in_array($enrollment->status, [
                Enrollment::STATUS_PENDING_PAYMENT,
                Enrollment::STATUS_PAYMENT_FAILED,
                Enrollment::STATUS_PROCESSING_PAYMENT,
            ], true)
            && $enrollment->expires_at->isPast()
        ) {
            $this->expire($enrollment);
        }
    }

    public function expire(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->isCompleted() || $enrollment->status === Enrollment::STATUS_EXPIRED) {
            return $enrollment;
        }

        $enrollment->forceFill(['status' => Enrollment::STATUS_EXPIRED])->save();

        // Pending provider payments are intentionally left open: a mobile-money
        // callback or status poll may confirm the charge after the reservation
        // lapsed, and a confirmed payment must still be able to create the
        // account within the payment grace window (see EnrollmentCompletionService).

        $this->auditLogger->log(
            'enrollment_expired',
            null,
            null,
            Enrollment::class,
            null,
            newValues: ['reference' => $enrollment->reference],
        );

        return $enrollment->fresh();
    }

    public function expireDueEnrollments(): int
    {
        $count = 0;

        Enrollment::query()
            ->whereIn('status', Enrollment::ACTIVE_RESERVATION_STATUSES)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (Enrollment $enrollment) use (&$count): void {
                $this->expire($enrollment);
                $count++;
            });

        return $count;
    }

    public function assertUsableForPayment(Enrollment $enrollment): void
    {
        $this->syncExpiry($enrollment);
        $enrollment->refresh();

        if ($enrollment->isCompleted()) {
            throw ValidationException::withMessages([
                'enrollment' => ['This enrollment has already been completed.'],
            ]);
        }

        if ($enrollment->isExpired() || $enrollment->status === Enrollment::STATUS_EXPIRED) {
            throw new HttpException(410, 'The registration payment session has expired.');
        }

        if ($enrollment->remainingAttempts() <= 0) {
            $this->invalidateAfterFailedAttempts($enrollment);
            throw new HttpException(410, 'Maximum payment attempts reached. Please register again.');
        }
    }

    public function invalidateAfterFailedAttempts(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->isCompleted()) {
            return $enrollment;
        }

        $enrollment->forceFill(['status' => Enrollment::STATUS_EXPIRED])->save();

        // Keep pending provider payments open so a late confirmation can still
        // be reconciled instead of silently losing a successful charge.

        $this->auditLogger->log(
            'enrollment_invalidated_after_failed_attempts',
            null,
            null,
            Enrollment::class,
            null,
            newValues: [
                'reference' => $enrollment->reference,
                'failed_payment_attempts' => $enrollment->failed_payment_attempts,
            ],
        );

        return $enrollment->fresh();
    }

    /**
     * @return array{subscription_fee: float, total_amount: float, currency: string}
     */
    public function resolvePricing(): array
    {
        $subscription = PlatformPricing::subscriptionMonthly();

        return [
            'subscription_fee' => $subscription,
            'total_amount' => $subscription,
            'currency' => PlatformPricing::currency(),
        ];
    }

    public function normalizeDomainName(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Str::lower(trim((string) $value));
    }

    public function generateReference(): string
    {
        do {
            $reference = 'ENR-'.strtoupper(Str::random(9));
        } while (Enrollment::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function assertEmailAvailable(string $email, ?string $ignoreEnrollmentId = null): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        $reserved = Enrollment::query()
            ->where('email', $email)
            ->whereIn('status', Enrollment::ACTIVE_RESERVATION_STATUSES)
            ->where('expires_at', '>', now())
            ->when($ignoreEnrollmentId, fn ($q) => $q->where('id', '!=', $ignoreEnrollmentId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'email' => ['This email already has a pending registration.'],
            ]);
        }
    }

    public function assertPhoneAvailable(string $phone, ?string $ignoreEnrollmentId = null): void
    {
        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is already registered.'],
            ]);
        }

        $reserved = Enrollment::query()
            ->where('phone', $phone)
            ->whereIn('status', Enrollment::ACTIVE_RESERVATION_STATUSES)
            ->where('expires_at', '>', now())
            ->when($ignoreEnrollmentId, fn ($q) => $q->where('id', '!=', $ignoreEnrollmentId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number already has a pending registration.'],
            ]);
        }
    }

    public function assertDomainAvailable(string $domainName, ?string $ignoreEnrollmentId = null): void
    {
        if (Company::query()->where('subdomain', $domainName)->exists()) {
            throw ValidationException::withMessages([
                'domain_name' => ['This domain name is already taken.'],
            ]);
        }

        $reserved = Enrollment::query()
            ->where('portal_subdomain', $domainName)
            ->whereIn('status', Enrollment::ACTIVE_RESERVATION_STATUSES)
            ->where('expires_at', '>', now())
            ->when($ignoreEnrollmentId, fn ($q) => $q->where('id', '!=', $ignoreEnrollmentId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'domain_name' => ['This domain name is reserved by another registration.'],
            ]);
        }
    }
}
