<?php

namespace Tests\Feature;

use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Services\RouterService;
use Tests\TestCase;

/**
 * End-to-end simulator test following legacy/flow.png.
 *
 * Assumes payment is done; asserts the client would be granted internet
 * (portal token verification returns Auth: 1) and therefore be listed online.
 */
class WifiDogSimulatorTest extends TestCase
{
    public function test_simulator_guarantees_internet_access(): void
    {
        $company = $this->createCompanyFor(
            $owner = $this->createUser(),
            'owner',
            ['subdomain' => 'juku'],
        );

        InternetPlan::query()->create([
            'company_id' => $company->id,
            'name' => '1 Hour',
            'slug' => '1-hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
            'status' => 'active',
        ]);

        $router = app(RouterService::class)->create($company, [
            'gateway_type' => NetworkDevice::GATEWAY_RUIJIE,
            'name' => 'Simulator Gateway',
            'lan_ip' => '192.168.0.144',
            'gateway_id' => 'SIMGW001',
            'wifidog_port' => 2060,
            'status' => 'active',
        ], $owner)->fresh();

        $response = $this->getJson(
            '/api/wifidog/test?gw_id=SIMGW001&mac=AA:BB:CC:DD:EE:FF&ip=192.168.0.50&url=http%3A%2F%2Fwww.google.com'
        );

        $response->assertOk()
            ->assertJsonPath('internet_granted', true)
            ->assertJsonPath('online', true)
            ->assertJsonPath('steps.4_verify_token.response', 'Auth: 1');

        $token = (string) $response->json('steps.2_login.token');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        // The AP's verify-token call must approve.
        $this->get('/api/wifidog/auth?stage=login&gw_id=SIMGW001&token='.$token.'&mac=AA:BB:CC:DD:EE:FF&ip=192.168.0.50')
            ->assertOk()
            ->assertSee('Auth: 1', false);

        // The captive session is authenticated (online) and linked to a hotspot session.
        $session = CaptiveSession::query()->where('token', $token)->firstOrFail();
        $this->assertTrue($session->isAuthenticated());
        $this->assertNotNull($session->network_session_id);
        $this->assertNotNull($session->access_grant_id);
    }
}
