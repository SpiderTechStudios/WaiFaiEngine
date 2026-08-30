<?php

namespace Tests\Feature;

use App\Models\PlatformPayment;
use App\Models\SignupIntent;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\PlatformPaymentService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignupTest extends TestCase
{
    public function test_can_create_signup_intent_without_creating_user(): void
    {
        $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.pricing.total_amount', '10000.00')
            ->assertJsonPath('data.pricing.subscription_fee', '10000.00')
            ->assertJsonPath('data.payment_phone', '0711987654')
            ->assertJsonPath('data.portal_subdomain', 'abc-internet');

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', [
            'email' => 'jane@example.com',
            'status' => 'pending_payment',
            'payment_phone' => '0711987654',
        ]);
    }

    public function test_rejects_missing_payment_phone(): void
    {
        $payload = $this->signupIntentPayload();
        unset($payload['payment_phone']);

        $this->postJson('/api/v1/signup/intents', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_phone'], 'data');
    }

    public function test_rejects_duplicate_email_and_subdomain_reservations(): void
    {
        $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())->assertCreated();

        $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload([
            'email' => 'other@example.com',
            'portal_subdomain' => 'abc-internet',
        ]))->assertStatus(422);

        $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload([
            'email' => 'jane@example.com',
            'portal_subdomain' => 'other-cafe',
        ]))->assertStatus(422);
    }

    public function test_payment_must_match_subscription_fee(): void
    {
        $intentId = $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->json('data.intent_id');

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'amount' => 100,
        ])->assertStatus(422);
    }

    public function test_cannot_complete_before_payment_is_paid(): void
    {
        $intentId = $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->json('data.intent_id');

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'amount' => 10000,
        ])->assertCreated();

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/complete')
            ->assertStatus(422);
    }

    public function test_paid_signup_creates_user_company_and_token(): void
    {
        Notification::fake();

        $result = $this->completePaidSignup([
            'portal_subdomain' => 'self-cafe',
        ]);

        $result['response']
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.current_company.name', 'ABC Internet')
            ->assertJsonPath('data.current_company.subdomain', 'self-cafe')
            ->assertJsonPath('data.current_company.subscription_status', 'active')
            ->assertJsonPath('data.membership.role.slug', 'owner')
            ->assertJsonStructure(['data' => ['token', 'user', 'companies', 'current_company']]);

        $this->assertDatabaseHas('signup_intents', [
            'id' => $result['intent_id'],
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('companies', [
            'subdomain' => 'self-cafe',
            'subscription_status' => 'active',
        ]);
        $this->assertDatabaseHas('platform_payments', [
            'id' => $result['payment_id'],
            'status' => 'paid',
            'type' => 'signup',
            'phone' => '0711987654',
            'amount' => '10000.00',
        ]);

        Notification::assertSentTo(
            User::query()->where('email', 'jane@example.com')->first(),
            VerifyEmailNotification::class,
        );
    }

    public function test_complete_is_idempotent(): void
    {
        $result = $this->completePaidSignup();

        $second = $this->postJson('/api/v1/signup/intents/'.$result['intent_id'].'/complete')
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'jane@example.com');

        $this->assertEquals(1, User::query()->where('email', 'jane@example.com')->count());
        $this->assertDatabaseCount('companies', 1);
        $this->assertNotEmpty($second->json('data.token'));
    }

    public function test_expired_intent_cannot_be_completed(): void
    {
        $intentId = $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->json('data.intent_id');

        $paymentId = $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'amount' => 10000,
        ])->assertCreated()->json('data.payment_id');

        app(PlatformPaymentService::class)->markPaid(
            PlatformPayment::query()->findOrFail($paymentId)
        );

        SignupIntent::query()->whereKey($intentId)->update([
            'expires_at' => now()->subHour(),
        ]);

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/complete')
            ->assertStatus(422);
    }

    public function test_payment_status_can_be_polled(): void
    {
        $intentId = $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->json('data.intent_id');

        $paymentId = $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments')
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '10000.00')
            ->json('data.payment_id');

        $this->getJson('/api/v1/signup/intents/'.$intentId.'/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }
}
