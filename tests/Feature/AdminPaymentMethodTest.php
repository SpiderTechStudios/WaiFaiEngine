<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Tests\TestCase;

class AdminPaymentMethodTest extends TestCase
{
    public function test_admin_can_list_payment_methods_including_prefixes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/payment-methods')
            ->assertOk()
            ->assertJsonPath('data.items.0.key', 'mixxbyyas');

        $airtel = collect($response->json('data.items'))->firstWhere('key', 'airtelmoney');

        $this->assertNotNull($airtel);
        $this->assertSame(['068', '069', '078'], $airtel['prefixes']);
    }

    public function test_admin_can_update_name_and_prefixes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $method = PaymentMethod::query()->where('key', 'airtelmoney')->firstOrFail();

        $this->withHeaders($this->authHeaders($admin))
            ->patchJson('/api/v1/admin/payment-methods/'.$method->id, [
                'name' => 'Airtel Money Tanzania',
                'prefixes' => ['78', '069', '068', '069'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Airtel Money Tanzania')
            ->assertJsonPath('data.prefixes', ['068', '069', '078']);

        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'name' => 'Airtel Money Tanzania',
        ]);
    }

    public function test_admin_can_add_and_remove_a_single_prefix(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $method = PaymentMethod::query()->where('key', 'halopesa')->firstOrFail();

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/payment-methods/'.$method->id.'/prefixes', ['prefix' => '63'])
            ->assertOk()
            ->assertJsonPath('data.prefixes', ['061', '062', '063']);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/payment-methods/'.$method->id.'/prefixes', ['prefix' => '063'])
            ->assertOk()
            ->assertJsonPath('data.prefixes', ['061', '062', '063']);

        $this->withHeaders($this->authHeaders($admin))
            ->deleteJson('/api/v1/admin/payment-methods/'.$method->id.'/prefixes/063')
            ->assertOk()
            ->assertJsonPath('data.prefixes', ['061', '062']);
    }

    public function test_superadmin_can_update_payment_method(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $method = PaymentMethod::query()->where('key', 'mpesa')->firstOrFail();

        $this->withHeaders($this->authHeaders($superadmin))
            ->putJson('/api/v1/superadmin/payment-methods/'.$method->id, [
                'name' => 'Vodacom M-Pesa',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Vodacom M-Pesa');
    }

    public function test_non_admin_cannot_change_payment_methods(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $method = PaymentMethod::query()->where('key', 'mpesa')->firstOrFail();

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson('/api/v1/admin/payment-methods/'.$method->id, ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $method = PaymentMethod::query()->where('key', 'mpesa')->firstOrFail();

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/payment-methods/'.$method->id.'/prefixes', ['prefix' => '1234'])
            ->assertUnprocessable()
            ->assertJsonPath('data.prefix.0', 'Prefix must be a 3-digit Tanzania network prefix such as 074 or 068.');
    }

    public function test_a_prefix_cannot_belong_to_two_networks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $airtel = PaymentMethod::query()->where('key', 'airtelmoney')->firstOrFail();

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/payment-methods/'.$airtel->id.'/prefixes', ['prefix' => '074'])
            ->assertUnprocessable()
            ->assertJsonPath('data.prefix.0', 'Prefix 074 is already assigned to M-Pesa.');

        $this->withHeaders($this->authHeaders($admin))
            ->patchJson('/api/v1/admin/payment-methods/'.$airtel->id, ['prefixes' => ['074']])
            ->assertUnprocessable()
            ->assertJsonPath('data.prefixes.0', 'Prefix 074 is already assigned to M-Pesa.');
    }

    public function test_removing_an_unknown_prefix_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $method = PaymentMethod::query()->where('key', 'mpesa')->firstOrFail();

        $this->withHeaders($this->authHeaders($admin))
            ->deleteJson('/api/v1/admin/payment-methods/'.$method->id.'/prefixes/099')
            ->assertUnprocessable()
            ->assertJsonPath('data.prefix.0', 'This prefix is not configured for the payment method.');
    }
}
