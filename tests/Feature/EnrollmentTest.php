<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\PlatformPaymentService;
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

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertStatus(410)
            ->assertJsonPath('data.enrollment_status', 'expired');

        $this->postJson('/api/v1/auth/enrollments/'.$reference.'/retry-payment')
            ->assertStatus(410);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PlatformPaymentService::class)->markPaid($payment->fresh());
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
