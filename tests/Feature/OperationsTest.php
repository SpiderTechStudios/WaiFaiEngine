<?php

namespace Tests\Feature;

use App\Models\NetworkDevice;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    public function test_owner_can_update_settings(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson('/api/v1/settings', [
                'primary_color' => '#112233',
                'voucher_code_digits' => 8,
                'captive_portal_welcome_message' => 'Karibu WiFi',
                'ruijie_account_id' => 'ruijie-1',
                'ruijie_password' => 'secret-pass',
                'payout_methods' => [
                    ['provider' => 'mpesa', 'phone' => '0700111222', 'name' => 'Jane'],
                ],
            ])->assertOk()
            ->assertJsonPath('data.primary_color', '#112233')
            ->assertJsonPath('data.voucher_code_digits', 8)
            ->assertJsonPath('data.ruijie_account_id', 'ruijie-1')
            ->assertJsonPath('data.ruijie_password_set', true);

        $response = $this->withHeaders($this->authHeaders($owner))
            ->getJson('/api/v1/settings')
            ->assertOk();

        $this->assertArrayNotHasKey('ruijie_password', $response->json('data'));
    }

    public function test_owner_can_manage_routers_packages_vouchers_and_payments(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->getJson('/api/v1/dashboard')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/device-setup')
            ->assertOk()
            ->assertJsonPath('data.methods.0.key', 'mikrotik')
            ->assertJsonPath('data.methods.1.key', 'ruijie_cloud');

        $router = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'name' => 'Main AP',
            'vendor' => 'mikrotik',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('network_devices', [
            'id' => $router['id'],
            'type' => 'router',
        ]);

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
            'badge' => 'Popular',
            'description' => 'Hourly hotspot access',
        ])->assertCreated()
            ->assertJsonPath('data.duration_unit', 'HOURS')
            ->assertJsonPath('data.badge', 'Popular')
            ->json('data.id');

        $vouchers = $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
            'internet_plan_id' => $packageId,
            'quantity' => 2,
        ])->assertCreated();

        $this->assertCount(2, $vouchers->json('data.vouchers'));
        $this->assertEquals(6, strlen($vouchers->json('data.vouchers.0.code')));

        $this->withHeaders($headers)->postJson('/api/v1/payments', [
            'internet_plan_id' => $packageId,
            'customer_name' => 'Walk in',
            'customer_phone' => '0700555666',
            'payment_method' => 'mpesa',
        ])->assertCreated()->assertJsonPath('data.status', 'paid');

        $this->withHeaders($headers)->getJson('/api/v1/payments')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/customers')->assertOk()
            ->assertJsonPath('data.meta.total', 1);
        $this->withHeaders($headers)->getJson('/api/v1/sessions')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/income')->assertOk()
            ->assertJsonPath('data.total', 1000);

        $this->withHeaders($headers)->postJson('/api/v1/withdrawals', [
            'amount' => 400,
            'provider' => 'mpesa',
            'destination_phone' => '0700111222',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->withHeaders($headers)->getJson('/api/v1/withdrawals')
            ->assertOk()
            ->assertJsonPath('data.wallet_balance', 600);
    }

    public function test_company_data_does_not_leak_between_tenants(): void
    {
        $ownerA = $this->createUser();
        $this->createCompanyFor($ownerA);
        $ownerB = $this->createUser();
        $this->createCompanyFor($ownerB);

        $routerId = $this->withHeaders($this->authHeaders($ownerA))
            ->postJson('/api/v1/routers', ['name' => 'A router', 'vendor' => 'ruijie'])
            ->json('data.id');

        $this->withHeaders($this->authHeaders($ownerB))
            ->getJson('/api/v1/routers/'.$routerId)
            ->assertNotFound();

        $this->withHeaders($this->authHeaders($ownerB))
            ->getJson('/api/v1/routers')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_router_is_scoped_to_current_company(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/routers', ['name' => 'Branch AP'])
            ->assertCreated();

        $this->assertEquals(1, NetworkDevice::query()->count());
    }
}
