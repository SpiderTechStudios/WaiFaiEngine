<?php

namespace Tests\Feature;

use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\StationPlan;
use App\Services\RouterService;
use Tests\TestCase;

class CaptivePortalTest extends TestCase
{
    public function test_connect_page_lists_active_plans(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'juku']);

        $this->createPlan($company, 'Daily 1GB', 1000, 'active');
        $this->createPlan($company, 'Weekly 5GB', 5000, 'active');
        $this->createPlan($company, 'Retired Plan', 9000, 'inactive');

        $this->get('/connect?subdomain=juku')
            ->assertOk()
            ->assertSee('Daily 1GB')
            ->assertSee('Weekly 5GB')
            ->assertDontSee('Retired Plan')
            ->assertSee('juku');
    }

    public function test_connect_page_scopes_plans_to_the_router_station(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'juku']);

        $linkedPlan = $this->createPlan($company, 'Router Plan', 2000, 'active');
        $otherPlan = $this->createPlan($company, 'Company Plan', 3000, 'active');

        $router = $this->createRouter($company, '58b4bb192d35', $owner);

        StationPlan::query()->create([
            'company_id' => $company->id,
            'network_station_id' => $router->network_station_id,
            'internet_plan_id' => $linkedPlan->id,
            'status' => 'active',
        ]);

        $this->get('/connect?subdomain=juku&router='.$router->gateway_id)
            ->assertOk()
            ->assertSee('Router Plan')
            ->assertDontSee('Company Plan');
    }

    public function test_connect_page_falls_back_to_all_plans_when_router_has_none(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'juku']);

        $this->createPlan($company, 'Company Plan', 3000, 'active');
        $router = $this->createRouter($company, '58b4bb192d35', $owner);

        $this->get('/connect?subdomain=juku&router='.$router->gateway_id)
            ->assertOk()
            ->assertSee('Company Plan');
    }

    public function test_connect_page_returns_404_for_unknown_subdomain(): void
    {
        $this->get('/connect?subdomain=missing')->assertNotFound();
    }

    public function test_connect_page_embeds_captive_session_for_authenticated_client(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'juku']);
        $router = $this->createRouter($company, '58b4bb192d35', $owner);

        $token = str_repeat('a', 64);
        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '58b4bb192d35',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.210',
            'gw_address' => '192.168.0.144',
            'gw_port' => 2060,
            'token' => $token,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->get('/connect?subdomain=juku&session='.$token)
            ->assertOk()
            ->assertSee($token)
            ->assertSee('juku');
    }

    private function createPlan(Company $company, string $name, int $price, string $status): InternetPlan
    {
        return InternetPlan::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => $price,
            'status' => $status,
        ]);
    }

    private function createRouter(Company $company, string $gwId, $owner): NetworkDevice
    {
        return app(RouterService::class)->create($company, [
            'gateway_type' => NetworkDevice::GATEWAY_RUIJIE,
            'name' => 'Gateway '.$gwId,
            'lan_ip' => '192.168.0.144',
            'gateway_id' => $gwId,
            'wifidog_port' => 2060,
            'status' => 'active',
        ], $owner)->fresh();
    }
}
