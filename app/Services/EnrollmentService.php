<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Enrollment;
use App\Models\PlatformPayment;
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

    /**
     * Start a new enrollment, or resume an incomplete one for the same email so
     * a missed USSD/STK push can be resent without forcing new registration details.
     *
     * @param  array<string, mixed>  $data
     * @return array{enrollment: Enrollment, resumed: bool}
     */
    public function createOrResume(array $data): array
    {
        $email = Str::lower(trim((string) $data['email']));

        $existing = Enrollment::query()
            ->where('email', $email)
            ->whereNull('user_id')
            ->whereNotIn('status', [Enrollment::STATUS_COMPLETED, Enrollment::STATUS_CANCELLED])
            ->latest('id')
            ->first();

        if ($existing && $this->isResumable($existing)) {
            if ((string) $existing->phone !== (string) $data['phone']) {
                throw ValidationException::withMessages([
                    'email' => ['This email already has a pending registration with a different phone number.'],
                ]);
            }

            $domainName = $this->normalizeDomainName($data['domain_name'] ?? $data['portal_subdomain'] ?? null);
            if ($domainName) {
                $this->assertDomainAvailable($domainName, $existing->id);
            }

            $existing->forceFill([
                'business_name' => $data['business_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'payment_phone' => $data['payment_phone'],
                'address' => $data['address'],
                'portal_subdomain' => $domainName ?? $existing->portal_subdomain,
                'password_hash' => Hash::make((string) $data['password']),
            ])->save();

            $enrollment = $this->prepareForPaymentRetry($existing->fresh());

            $this->auditLogger->log(
                'enrollment_resumed_for_payment_retry',
                null,
                null,
                Enrollment::class,
                null,
                newValues: [
                    'enrollment_id' => $enrollment->id,
                    'reference' => $enrollment->reference,
                    'email' => $enrollment->email,
                ],
            );

            return ['enrollment' => $enrollment, 'resumed' => true];
        }

        return ['enrollment' => $this->create($data), 'resumed' => false];
    }

    /**
     * Whether the customer can resend a USSD/STK push on this enrollment.
     */
    public function isResumable(Enrollment $enrollment): bool
    {
        if ($enrollment->isCompleted()) {
            return false;
        }

        if ($enrollment->payments()->where('status', PlatformPayment::STATUS_PAID)->exists()) {
            return false;
        }

        if ($enrollment->remainingAttempts() <= 0) {
            return false;
        }

        $this->syncExpiry($enrollment);
        $enrollment->refresh();

        if ($enrollment->canRetryPayment()) {
            return true;
        }

        return $enrollment->status === Enrollment::STATUS_EXPIRED
            && $this->withinPaymentGrace($enrollment);
    }

    /**
     * Revive / extend an enrollment so another provider push can be initiated.
     */
    public function prepareForPaymentRetry(Enrollment $enrollment): Enrollment
    {
        $this->syncExpiry($enrollment);
        $enrollment->refresh();

        if ($enrollment->isCompleted()) {
            throw ValidationException::withMessages([
                'enrollment' => ['This enrollment has already been completed.'],
            ]);
        }

        if ($enrollment->payments()->where('status', PlatformPayment::STATUS_PAID)->exists()) {
            throw ValidationException::withMessages([
                'enrollment' => ['Payment has already succeeded for this enrollment.'],
            ]);
        }

        if ($enrollment->remainingAttempts() <= 0) {
            $this->invalidateAfterFailedAttempts($enrollment);
            throw new HttpException(410, 'Maximum payment attempts reached. Please register again.');
        }

        if (
            ! $enrollment->canRetryPayment()
            && ! (
                $enrollment->status === Enrollment::STATUS_EXPIRED
                && $this->withinPaymentGrace($enrollment)
            )
        ) {
            throw new HttpException(410, 'The registration payment session has expired.');
        }

        return $this->extendReservation($enrollment);
    }

    public function extendReservation(Enrollment $enrollment): Enrollment
    {
        $ttlMinutes = max(1, (int) config('platform.enrollment_ttl_minutes', 4));

        $enrollment->forceFill([
            'status' => Enrollment::STATUS_PENDING_PAYMENT,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ])->save();

        return $enrollment->fresh();
    }

    public function withinPaymentGrace(Enrollment $enrollment): bool
    {
        if (! $enrollment->expires_at) {
            return true;
        }

        $graceMinutes = max(0, (int) config('platform.enrollment_payment_grace_minutes', 60));

        return now()->lessThanOrEqualTo(
            $enrollment->expires_at->copy()->addMinutes($graceMinutes)
        );
    }

    public function assertUsableForPayment(Enrollment $enrollment): void
    {
        $this->prepareForPaymentRetry($enrollment);
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
