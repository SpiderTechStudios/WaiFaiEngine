<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\EnrollmentCompletionService;
use App\Services\PlatformPaymentService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    public function test_register_creates_enrollment_without_user_or_company(): void
    {
        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.enrollment_status', 'pending_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.amount', '10000.00')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.payment_phone', '0711987654')
            ->assertJsonPath('data.remaining_attempts', 3)
            ->assertJsonStructure(['data' => ['enrollment_reference', 'expires_at', 'payment']]);

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', [
            'email' => 'jane@example.com',
            'status' => 'pending_payment',
            'payment_phone' => '0711987654',
            'portal_subdomain' => 'abc-internet',
        ]);
        $this->assertDatabaseHas('platform_payments', [
            'type' => 'platform_subscription',
            'status' => 'pending',
            'phone' => '0711987654',
            'amount' => '10000.00',
        ]);
    }

    public function test_rejects_missing_payment_phone(): void
    {
        $payload = $this->enrollmentPayload();
        unset($payload['payment_phone']);

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_phone'], 'data');
    }

    public function test_rejects_duplicate_email_phone_and_domain_reservations(): void
    {
        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'email' => 'other@example.com',
            'phone' => '0700999888',
            'domain_name' => 'abc-internet',
        ]))->assertStatus(422);

        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'email' => 'jane@example.com',
            'phone' => '0700999888',
            'domain_name' => 'other-cafe',
        ]))->assertStatus(422);

        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'email' => 'other@example.com',
            'phone' => '0700123456',
            'domain_name' => 'other-cafe',
        ]))->assertStatus(422);
    }

    public function test_missed_ussd_can_be_resent_via_retry_payment(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $firstPaymentId = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->value('id');

        $this->postJson('/api/v1/auth/enrollments/'.$reference.'/retry-payment')
            ->assertOk()
            ->assertJsonPath('data.enrollment_reference', $reference)
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.can_retry_payment', true)
            ->assertJsonPath('data.next_action', 'retry_payment');

        $enrollment = Enrollment::query()->where('reference', $reference)->firstOrFail();
        $this->assertSame('pending_payment', $enrollment->status);
        $this->assertTrue($enrollment->expires_at->greaterThan(now()->addMinutes(3)));
        $this->assertDatabaseHas('platform_payments', [
            'id' => $firstPaymentId,
            'status' => 'cancelled',
        ]);
        $this->assertSame(2, $enrollment->payments()->count());
    }

    public function test_reregister_with_same_details_resends_ussd_instead_of_rejecting(): void
    {
        $first = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data');

        $second = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'payment_phone' => '0711987000',
        ]))
            ->assertOk()
            ->assertJsonPath('data.enrollment_reference', $first['enrollment_reference'])
            ->assertJsonPath('data.payment_phone', '0711987000')
            ->assertJsonPath('data.can_retry_payment', true)
            ->json('data');

        $this->assertSame($first['enrollment_reference'], $second['enrollment_reference']);
        $this->assertDatabaseCount('signup_intents', 1);
        $this->assertSame(
            2,
            Enrollment::query()->where('reference', $first['enrollment_reference'])->firstOrFail()->payments()->count()
        );
    }

    public function test_retry_payment_after_soft_expiry_within_grace_resends_ussd(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinute(),
            'status' => Enrollment::STATUS_EXPIRED,
        ]);

        $this->postJson('/api/v1/auth/enrollments/'.$reference.'/retry-payment')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'pending_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.can_retry_payment', true);

        $enrollment = Enrollment::query()->where('reference', $reference)->firstOrFail();
        $this->assertTrue($enrollment->expires_at->isFuture());
    }

    public function test_paid_callback_creates_user_company_and_allows_login(): void
    {
        Notification::fake();

        $result = $this->completePaidSignup([
            'domain_name' => 'self-cafe',
        ]);

        $result['response']
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.current_company.name', 'ABC Internet')
            ->assertJsonPath('data.current_company.subdomain', 'self-cafe')
            ->assertJsonPath('data.current_company.subscription_status', 'active')
            ->assertJsonPath('data.membership.role.slug', 'owner')
            ->assertJsonStructure(['data' => ['token', 'user', 'companies', 'current_company']]);

        $this->assertDatabaseHas('signup_intents', [
            'id' => $result['enrollment_id'],
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('companies', [
            'subdomain' => 'self-cafe',
            'subscription_status' => 'active',
        ]);
        $this->assertDatabaseHas('platform_payments', [
            'id' => $result['payment_id'],
            'status' => 'paid',
            'type' => 'platform_subscription',
        ]);

        Notification::assertSentTo(
            User::query()->where('email', 'jane@example.com')->first(),
            VerifyEmailNotification::class,
        );
    }

    public function test_enrollment_completion_survives_verification_email_failure(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $payment->forceFill([
            'status' => PlatformPayment::STATUS_PAID,
            'paid_at' => now(),
            'processed_at' => now(),
        ])->save();

        Event::listen(
            Registered::class,
            fn () => throw new \RuntimeException('smtp down'),
        );

        app(EnrollmentCompletionService::class)->completeFromPayment($payment);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', ['reference' => $reference, 'status' => 'completed']);
    }

    public function test_complete_paid_enrollments_command_creates_missing_accounts(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $payment->forceFill([
            'status' => PlatformPayment::STATUS_PAID,
            'paid_at' => now(),
            'processed_at' => now(),
        ])->save();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $this->artisan('enrollments:complete-paid')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', ['reference' => $reference, 'status' => 'completed']);
    }

    public function test_payment_callback_replay_is_idempotent(): void
    {
        $result = $this->completePaidSignup();

        $payment = PlatformPayment::query()->findOrFail($result['payment_id']);
        app(PlatformPaymentService::class)->markPaid($payment);
        app(PlatformPaymentService::class)->markPaid($payment);

        $this->assertEquals(1, User::query()->where('email', 'jane@example.com')->count());
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_cannot_login_before_payment_succeeds(): void
    {
        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_failed_payments_allow_retry_until_third_failure(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $enrollment = Enrollment::query()->where('reference', $reference)->firstOrFail();

        for ($i = 1; $i <= 2; $i++) {
            $payment = $enrollment->payments()->latest('id')->firstOrFail();
            app(PlatformPaymentService::class)->markFailed($payment, 'declined');

            $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
                ->assertOk()
                ->assertJsonPath('data.enrollment_status', 'payment_failed')
                ->assertJsonPath('data.remaining_attempts', 3 - $i);

            $this->postJson('/api/v1/auth/enrollments/'.$reference.'/retry-payment', [
                'payment_phone' => '071100000'.$i,
            ])->assertOk()
                ->assertJsonPath('data.payment_status', 'pending');

            $enrollment->refresh();
        }

        $payment = $enrollment->payments()->latest('id')->firstOrFail();
        app(PlatformPaymentService::class)->markFailed($payment, 'declined');

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertStatus(410)
            ->assertJsonPath('data.enrollment_status', 'expired');

        $this->postJson('/api/v1/auth/enrollments/'.$reference.'/retry-payment')
            ->assertStatus(410);

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_enrollment_expires_after_four_minutes(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinute(),
        ]);

        // Pending charge is still within the payment grace window — keep polling.
        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'expired');

        $graceMinutes = (int) config('platform.enrollment_payment_grace_minutes', 60);

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinutes($graceMinutes + 5),
        ]);

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertStatus(410)
            ->assertJsonPath('data.enrollment_status', 'expired');

        $this->postJson('/api/v1/auth/enrollments/'.$reference.'/retry-payment')
            ->assertStatus(410);
    }

    public function test_confirmed_payment_after_expiry_completes_within_grace(): void
    {
        Notification::fake();

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinute(),
        ]);

        app(PlatformPaymentService::class)->markPaid($payment->fresh());

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', [
            'reference' => $reference,
            'status' => 'completed',
        ]);
    }

    public function test_confirmed_payment_after_grace_still_creates_account(): void
    {
        Notification::fake();

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $graceMinutes = (int) config('platform.enrollment_payment_grace_minutes', 60);

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinutes($graceMinutes + 5),
            'status' => Enrollment::STATUS_EXPIRED,
        ]);

        app(PlatformPaymentService::class)->markPaid($payment->fresh());

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', [
            'reference' => $reference,
            'status' => 'completed',
        ]);
        $this->assertTrue((bool) data_get($payment->fresh()->metadata, 'completed_after_grace_window'));
    }

    public function test_paid_but_incomplete_enrollment_stays_pollable(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $graceMinutes = (int) config('platform.enrollment_payment_grace_minutes', 60);

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinutes($graceMinutes + 5),
            'status' => Enrollment::STATUS_EXPIRED,
        ]);

        $payment->forceFill([
            'status' => PlatformPayment::STATUS_PAID,
            'paid_at' => now(),
            'processed_at' => now(),
        ])->save();

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'completed')
            ->assertJsonPath('data.account_created', true);
    }

    public function test_expired_domain_reservation_is_released(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'domain_name' => 'reserved-wifi',
        ]))->assertCreated()->json('data.enrollment_reference');

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('enrollments:expire')->assertSuccessful();

        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'email' => 'new@example.com',
            'phone' => '0700777666',
            'domain_name' => 'reserved-wifi',
        ]))->assertCreated();
    }

    public function test_webhook_marks_payment_and_completes_enrollment(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->postJson('/api/v1/webhooks/payments/stub', [
            'reference' => $payment->reference,
            'status' => 'paid',
            'amount' => 10000,
            'currency' => 'TZS',
        ], [
            'X-Platform-Payment-Secret' => 'stub-webhook-secret',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'completed');
    }

    public function test_frontend_cannot_activate_account_without_payment(): void
    {
        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())->assertCreated();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_payment_status_polling_while_pending(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'pending_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.remaining_attempts', 3);
    }
}
