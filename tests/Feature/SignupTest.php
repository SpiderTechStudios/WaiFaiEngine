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
            ->assertJsonPath('data.setup_type', 'assisted')
            ->assertJsonPath('data.pricing.total_amount', '160000.00')
            ->assertJsonPath('data.portal_subdomain', 'abc-internet');

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', [
            'email' => 'jane@example.com',
            'status' => 'pending_payment',
        ]);
    }

    public function test_rejects_manipulated_pricing(): void
    {
        $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload([
            'installation_fee' => 1,
            'total_amount' => 10001,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['installation_fee'], 'data');
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

    public function test_payment_must_match_intent_total(): void
    {
        $intentId = $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->json('data.intent_id');

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'amount' => 100,
            'line_items' => [
                ['code' => 'installation', 'amount' => 150000],
                ['code' => 'subscription', 'amount' => 10000],
            ],
        ])->assertStatus(422);
    }

    public function test_cannot_complete_before_payment_is_paid(): void
    {
        $intentId = $this->postJson('/api/v1/signup/intents', $this->signupIntentPayload())
            ->assertCreated()
            ->json('data.intent_id');

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'amount' => 160000,
            'line_items' => [
                ['code' => 'installation', 'amount' => 150000],
                ['code' => 'subscription', 'amount' => 10000],
            ],
        ])->assertCreated();

        $this->postJson('/api/v1/signup/intents/'.$intentId.'/complete')
            ->assertStatus(422);
    }

    public function test_paid_signup_creates_user_company_and_token(): void
    {
        Notification::fake();

        $result = $this->completePaidSignup([
            'setup_type' => 'self',
            'portal_subdomain' => 'self-cafe',
        ]);

        $result['response']
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.current_company.name', 'ABC Internet')
            ->assertJsonPath('data.current_company.subdomain', 'self-cafe')
            ->assertJsonPath('data.current_company.setup_type', 'self')
            ->assertJsonPath('data.current_company.subscription_status', 'active')
            ->assertJsonPath('data.membership.role.slug', 'owner')
            ->assertJsonStructure(['data' => ['token', 'user', 'companies', 'current_company']]);

        $this->assertDatabaseHas('signup_intents', [
            'id' => $result['intent_id'],
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('companies', [
            'subdomain' => 'self-cafe',
            'setup_type' => 'self',
            'subscription_status' => 'active',
        ]);
        $this->assertDatabaseHas('platform_payments', [
            'id' => $result['payment_id'],
            'status' => 'paid',
            'type' => 'signup',
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
            'amount' => 160000,
            'line_items' => [
                ['code' => 'installation', 'amount' => 150000],
                ['code' => 'subscription', 'amount' => 10000],
            ],
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

        $paymentId = $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'amount' => 160000,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.payment_id');

        $this->getJson('/api/v1/signup/intents/'.$intentId.'/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }
}
