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
                'payment_method' => 'both',
            ])->assertOk()
            ->assertJsonPath('data.primary_color', '#112233')
            ->assertJsonPath('data.voucher_code_digits', 8)
            ->assertJsonPath('data.ruijie_account_id', 'ruijie-1')
            ->assertJsonPath('data.payment_method', 'both')
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
            'gateway_type' => 'mikrotik',
            'name' => 'MikroTik-Hotspot',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()
            ->assertJsonPath('data.gateway_type', 'mikrotik')
            ->assertJsonPath('data.api_password_set', true)
            ->json('data');

        $this->assertDatabaseHas('network_devices', [
            'id' => $router['id'],
            'type' => 'router',
            'gateway_type' => 'mikrotik',
            'lan_ip' => '192.168.88.1',
        ]);
        $this->assertArrayNotHasKey('api_password', $router);

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
            'router_id' => $router['id'],
            'package_id' => $packageId,
            'quantity' => 2,
            'max_uses' => 1,
            'note' => 'Front desk pack',
        ])->assertCreated()
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.items.0.status', 'active');

        $this->assertCount(2, $vouchers->json('data.items'));
        $this->assertEquals(6, strlen($vouchers->json('data.items.0.code')));

        $voucherId = $vouchers->json('data.items.0.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/vouchers/'.$voucherId.'/revoke')
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->withHeaders($headers)->getJson('/api/v1/vouchers?status=revoked')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

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
            ->postJson('/api/v1/routers', [
                'gateway_type' => 'ruijie',
                'name' => 'Ruijie AP',
                'lan_ip' => '192.168.88.1',
                'gateway_id' => 'G1UQCC8000976',
                'wifidog_port' => 2060,
            ])
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
            ->postJson('/api/v1/routers', [
                'gateway_type' => 'wavlink',
                'name' => 'PELEKA-WAVLINK',
                'lan_ip' => '192.168.10.1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gateway_type', 'wavlink');

        $this->assertEquals(1, NetworkDevice::query()->count());
    }

    public function test_mikrotik_rejects_private_api_host(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/routers', [
                'gateway_type' => 'mikrotik',
                'name' => 'Bad Host Router',
                'lan_ip' => '192.168.88.1',
                'api_host' => '192.168.1.1',
                'api_username' => 'admin',
                'api_password' => 'secret',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.api_host.0', 'API host must be a public IP or DDNS hostname. Do not use private addresses like 192.168.x or 10.x.');
    }

    public function test_custom_voucher_code_requires_quantity_one(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $routerId = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'wavlink',
            'name' => 'Shop Wavlink',
        ])->json('data.id');

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => 'Day Pass',
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => 2000,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
            'router_id' => $routerId,
            'package_id' => $packageId,
            'quantity' => 2,
            'custom_code' => '998877',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
            'router_id' => $routerId,
            'package_id' => $packageId,
            'quantity' => 1,
            'custom_code' => '998877',
            'max_uses' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.code', '998877')
            ->assertJsonPath('data.items.0.max_uses', 3);
    }
}
