<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminRouterTest extends TestCase
{
    public function test_platform_admin_can_list_routers_across_all_clients(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $ownerA = $this->createUser();
        $companyA = $this->createCompanyFor($ownerA, 'owner', ['name' => 'Client A']);
        $ownerB = $this->createUser();
        $companyB = $this->createCompanyFor($ownerB, 'owner', ['name' => 'Client B']);

        $this->createRouterFor($companyA, 'mikrotik', 'Router A');
        $this->createRouterFor($companyB, 'ruijie', 'Router B');

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/routers')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_superadmin_can_manage_client_router(): void
    {
        Http::fake([
            'https://ruijie.test/devices/*' => Http::response([
                'serial_number' => 'SN-UPDATED',
                'online' => true,
            ]),
        ]);

        config([
            'services.ruijie.base_url' => 'https://ruijie.test',
        ]);

        $superadmin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', [
            'name' => 'Hotspot Ltd',
            'ruijie_account_id' => 'ruijie-user',
            'ruijie_password' => Crypt::encryptString('ruijie-pass'),
        ]);

        $created = $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/admin/routers', [
                'company_id' => $company->id,
                'gateway_type' => 'ruijie',
                'name' => 'Ruijie AP',
                'lan_ip' => '192.168.88.1',
                'gateway_id' => 'G1UQCC8000976',
                'wifidog_port' => 2060,
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.name', 'Hotspot Ltd')
            ->assertJsonPath('data.gateway_type', 'ruijie')
            ->json('data');

        $routerId = $created['id'];

        $this->withHeaders($this->authHeaders($superadmin))
            ->getJson('/api/v1/admin/routers/'.$routerId)
            ->assertOk()
            ->assertJsonPath('data.router.id', $routerId)
            ->assertJsonPath('data.company.id', $company->id);

        $this->withHeaders($this->authHeaders($superadmin))
            ->patchJson('/api/v1/admin/routers/'.$routerId, [
                'name' => 'Updated Ruijie AP',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Ruijie AP');

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/admin/routers/'.$routerId.'/sync')
            ->assertOk()
            ->assertJsonPath('data.serial_number', 'SN-UPDATED')
            ->assertJsonPath('data.status', 'active');

        $this->withHeaders($this->authHeaders($superadmin))
            ->deleteJson('/api/v1/admin/routers/'.$routerId)
            ->assertOk();

        $this->assertSoftDeleted('network_devices', ['id' => $routerId]);
    }

    public function test_admin_router_list_can_filter_by_company(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $ownerA = $this->createUser();
        $companyA = $this->createCompanyFor($ownerA);
        $ownerB = $this->createUser();
        $companyB = $this->createCompanyFor($ownerB);

        $this->createRouterFor($companyA);
        $this->createRouterFor($companyB);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/routers?company_id='.$companyA->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.company.id', $companyA->id);
    }

    public function test_company_owner_cannot_access_admin_router_routes(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->getJson('/api/v1/admin/routers')
            ->assertForbidden();
    }

    public function test_sync_rejects_non_ruijie_router(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $router = $this->createRouterFor($company, 'mikrotik', 'MikroTik');

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/routers/'.$router->id.'/sync')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only Ruijie routers can be synced from Ruijie Cloud.');
    }

    private function createRouterFor(
        Company $company,
        string $gatewayType = 'wavlink',
        string $name = 'Test Router',
    ): NetworkDevice {
        $owner = $company->creator ?? $this->createUser();

        return app(\App\Services\RouterService::class)->create($company, [
            'gateway_type' => $gatewayType,
            'name' => $name,
            'lan_ip' => $gatewayType === 'wavlink' ? '192.168.10.1' : '192.168.88.1',
            'gateway_id' => $gatewayType === 'ruijie' ? 'G1TEST0001' : null,
            'api_username' => $gatewayType === 'mikrotik' ? 'admin' : null,
            'api_password' => $gatewayType === 'mikrotik' ? 'secret' : null,
        ], $owner);
    }
}
