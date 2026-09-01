<?php

namespace Tests\Feature;

use App\Models\PaymentProvider;
use App\Models\User;
use Tests\TestCase;

class PaymentProviderTest extends TestCase
{
    public function test_superadmin_can_list_and_manage_providers(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $response = $this->withHeaders($this->authHeaders($superadmin))
            ->getJson('/api/v1/superadmin/payment-providers')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('stub', $slugs);
        $this->assertContains('flutterwave', $slugs);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/payment-providers', [
                'name' => 'AzamPay',
                'slug' => 'azampay',
                'supports_payments' => true,
                'supports_payouts' => true,
                'credentials' => [
                    'public_key' => 'pub-test',
                    'secret_key' => 'sec-test',
                ],
            ])->assertCreated()
            ->assertJsonPath('data.slug', 'azampay')
            ->assertJsonMissingPath('data.credentials.secret_key')
            ->assertJsonPath('data.credentials.has_secret_key', true);

        $azam = PaymentProvider::query()->where('slug', 'azampay')->firstOrFail();

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/payment-providers/azampay/default-payments')
            ->assertOk()
            ->assertJsonPath('data.is_default_for_payments', true);

        $this->assertFalse(PaymentProvider::query()->where('slug', 'stub')->value('is_default_for_payments'));
        $this->assertTrue($azam->fresh()->is_default_for_payments);
    }

    public function test_non_superadmin_cannot_manage_providers(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/superadmin/payment-providers')
            ->assertForbidden();
    }

    public function test_registration_uses_default_provider(): void
    {
        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.payment.provider', 'stub')
            ->assertJsonPath('data.payment.purpose', 'platform_subscription');
    }

    public function test_provider_webhook_rejects_invalid_signature(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = \App\Models\Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->postJson('/api/v1/webhooks/payments/stub', [
            'reference' => $payment->reference,
            'status' => 'paid',
            'amount' => 10000,
            'currency' => 'TZS',
        ], [
            'X-Platform-Payment-Secret' => 'wrong-secret',
        ])->assertUnauthorized();
    }
}
