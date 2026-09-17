<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Services\RouterService;
use Tests\TestCase;

class WiFiDogTest extends TestCase
{
    public function test_unknown_gateway_returns_404(): void
    {
        $this->get('/api/wifidog/login?gw_id=unknown')
            ->assertNotFound()
            ->assertJsonPath('message', 'Unknown WiFiDog gateway. No router is registered with gateway_id "unknown".');
    }

    public function test_inactive_gateway_returns_404(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323', 'inactive');

        $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35')
            ->assertNotFound()
            ->assertJsonPath('message', 'WiFiDog gateway "323" is registered but inactive (status: inactive).');
    }

    public function test_gateway_id_match_is_case_insensitive(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test/connect',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, 'G1ABC123');

        $this->get('/api/wifidog/login?gw_id=g1abc123&ip=192.168.0.35')
            ->assertRedirect()
            ->assertHeader('Location');
    }

    public function test_dev_id_is_accepted_as_gateway_identifier(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test/connect',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, 'DEV-99');

        $this->get('/api/wifidog/login?dev_id=DEV-99&ip=192.168.0.35')
            ->assertRedirect();
    }

    public function test_valid_gateway_redirects_to_connect_portal(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test/connect',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $response = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://waifai.test/connect?', $location);
        $this->assertStringNotContainsString('/api/wifidog', $location);
        $this->assertStringNotContainsString('/login/login', $location);
        $this->assertStringContainsString('subdomain=spider', $location);
        $this->assertMatchesRegularExpression('/session=[a-f0-9]{64}/', $location);

        $this->assertDatabaseCount('captive_sessions', 1);
        $this->assertDatabaseHas('captive_sessions', [
            'gateway_id' => '323',
            'client_ip' => '192.168.0.35',
            'status' => CaptiveSession::STATUS_PENDING,
            'company_id' => $company->id,
        ]);
    }

    public function test_portal_url_origin_appends_connect_path(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test',
            'captive.portal_base_url' => 'https://waifai.test',
            'captive.portal_connect_path' => '/connect',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $location = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://waifai.test/connect?', $location);
    }

    public function test_misconfigured_portal_url_pointing_at_wifidog_api_is_rejected(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/api/wifidog/login',
            'captive.portal_base_url' => 'https://waifai.test/api/wifidog/login',
            'captive.portal_connect_path' => '/login',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35')
            ->assertStatus(500);
    }

    public function test_valid_gateway_with_mac_creates_session(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35&mac=AA:BB:CC:DD:EE:FF')
            ->assertRedirect();

        $this->assertDatabaseHas('captive_sessions', [
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.35',
            'status' => CaptiveSession::STATUS_PENDING,
        ]);
    }

    public function test_duplicate_login_reuses_pending_session(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $first = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35&mac=AA:BB:CC:DD:EE:FF')
            ->assertRedirect();
        $second = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.40&mac=AA:BB:CC:DD:EE:FF')
            ->assertRedirect();

        $this->assertDatabaseCount('captive_sessions', 1);
        $this->assertSame(
            $this->sessionTokenFromLocation($first->headers->get('Location')),
            $this->sessionTokenFromLocation($second->headers->get('Location')),
        );
        $this->assertDatabaseHas('captive_sessions', [
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.40',
        ]);
    }

    public function test_subdomain_query_cannot_hijack_tenant(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $ownerA = $this->createUser();
        $companyA = $this->createCompanyFor($ownerA, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($companyA, '323');

        $ownerB = $this->createUser();
        $this->createCompanyFor($ownerB, 'owner', ['subdomain' => 'other-co']);

        $location = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35&subdomain=other-co&company_id=999&network_id=1')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('subdomain=spider', $location);
        $this->assertStringNotContainsString('subdomain=other-co', $location);
    }

    public function test_ping_returns_pong_and_updates_last_seen(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $router = $this->createRuijieRouter($company, '323');

        $this->get('/api/wifidog/ping?gw_id=323&sys_uptime=100')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Pong', false);

        $this->assertNotNull($router->fresh()->last_seen_at);
    }

    public function test_auth_denies_pending_and_allows_authenticated(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $router = $this->createRuijieRouter($company, '323');

        $pending = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.35',
            'gw_address' => '192.168.0.1',
            'gw_port' => 2060,
            'token' => str_repeat('a', 64),
            'status' => CaptiveSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->get('/api/wifidog/auth?stage=login&token='.$pending->token.'&mac=AA:BB:CC:DD:EE:FF&gw_id=323')
            ->assertOk()
            ->assertSee('Auth: 0', false);

        $plan = InternetPlan::query()->create([
            'company_id' => $company->id,
            'name' => '1 Hour',
            'slug' => '1-hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'name' => 'Guest',
            'phone' => '0711000000',
            'status' => 'active',
        ]);
        $grant = AccessGrant::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'voucher',
            'starts_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $authToken = str_repeat('b', 64);
        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.35',
            'gw_address' => '192.168.0.1',
            'gw_port' => 2060,
            'token' => $authToken,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->get('/api/wifidog/auth?stage=login&token='.$authToken.'&mac=AA:BB:CC:DD:EE:FF&gw_id=323')
            ->assertOk()
            ->assertSee('Auth: 1', false);
    }

    public function test_expired_session_is_not_authorized(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $router = $this->createRuijieRouter($company, '323');

        $token = str_repeat('c', 64);
        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'token' => $token,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get('/api/wifidog/auth?stage=login&token='.$token)
            ->assertOk()
            ->assertSee('Auth: 0', false);

        $this->assertDatabaseHas('captive_sessions', [
            'token' => $token,
            'status' => CaptiveSession::STATUS_EXPIRED,
        ]);
    }

    public function test_captive_session_api_returns_safe_payload(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $location = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35&mac=AA:BB:CC:DD:EE:FF')
            ->assertRedirect()
            ->headers->get('Location');
        $token = $this->sessionTokenFromLocation($location);

        $this->getJson('/api/v1/captive/sessions/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.network.subdomain', 'spider')
            ->assertJsonPath('data.client.ip', '192.168.0.35')
            ->assertJsonMissingPath('data.company_id')
            ->assertJsonMissingPath('data.network_device_id');
    }

    public function test_captive_session_api_returns_gateway_auth_url_before_authentication(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $location = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35&mac=AA:BB:CC:DD:EE:FF')
            ->assertRedirect()
            ->headers->get('Location');
        $token = $this->sessionTokenFromLocation($location);

        $this->getJson('/api/v1/captive/sessions/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath(
                'data.gateway_auth_url',
                'http://192.168.0.1:2060/wifidog/auth?token='.$token,
            );
    }

    public function test_portal_redirects_denied_client_to_captive_portal(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $this->createRuijieRouter($company, '323');

        $location = $this->get('/api/wifidog/portal?gw_id=323&ip=192.168.0.35&mac=AA:BB:CC:DD:EE:FF&url=https://www.google.com/')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://waifai.test/connect?', $location);
        $this->assertStringContainsString('subdomain=spider', $location);
        $this->assertMatchesRegularExpression('/session=[a-f0-9]{64}/', $location);
        $this->assertStringNotContainsString('google.com', $location);
        $this->assertDatabaseCount('captive_sessions', 1);
        $this->assertDatabaseHas('captive_sessions', [
            'gateway_id' => '323',
            'status' => CaptiveSession::STATUS_PENDING,
        ]);
    }

    public function test_portal_redirects_authenticated_client_to_original_url(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $router = $this->createRuijieRouter($company, '323');

        $token = str_repeat('e', 64);
        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.35',
            'gw_address' => '192.168.0.1',
            'gw_port' => 2060,
            'requested_url' => 'https://www.google.com/',
            'token' => $token,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->get('/api/wifidog/portal?gw_id=323&token='.$token)
            ->assertRedirect('https://www.google.com/');
    }

    public function test_portal_authenticated_client_falls_back_to_configured_success_url(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
            'captive.portal_success_url' => 'http://www.google.com',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $router = $this->createRuijieRouter($company, '323');

        $token = str_repeat('f', 64);
        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.35',
            'gw_address' => '192.168.0.1',
            'gw_port' => 2060,
            'requested_url' => null,
            'token' => $token,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->get('/api/wifidog/portal?gw_id=323&token='.$token)
            ->assertRedirect('http://www.google.com');
    }

    public function test_authenticated_login_redirects_to_gateway_auth(): void
    {
        config([
            'captive.portal_url' => 'https://waifai.test/connect',
            'captive.portal_base_url' => 'https://waifai.test',
        ]);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'spider']);
        $router = $this->createRuijieRouter($company, '323');

        $token = str_repeat('d', 64);
        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => '323',
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.35',
            'gw_address' => '192.168.0.1',
            'gw_port' => 2060,
            'token' => $token,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $location = $this->get('/api/wifidog/login?gw_id=323&ip=192.168.0.35&mac=AA:BB:CC:DD:EE:FF&gw_address=192.168.0.1&gw_port=2060')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('http://192.168.0.1:2060/wifidog/auth?token=', $location);
        $this->assertDatabaseCount('captive_sessions', 1);
    }

    public function test_gateway_auth_url_falls_back_to_router_lan_ip_when_gw_address_missing(): void
    {
        config(['captive.portal_url' => 'https://portal.example.test/connect']);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', [
            'subdomain' => 'lan-fallback',
            'status' => 'active',
        ]);
        $this->createRuijieRouter($company, '324');

        $location = $this->get('/api/wifidog/login?gw_id=324&ip=192.168.0.40&mac=AA:BB:CC:DD:EE:11')
            ->assertRedirect()
            ->headers->get('Location');

        $token = $this->sessionTokenFromLocation($location);
        $this->assertNotSame('', $token);

        $session = CaptiveSession::query()->where('token', $token)->firstOrFail();
        $this->assertSame('192.168.0.1', $session->gw_address);

        $session->forceFill([
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ])->save();

        $url = app(\App\Services\CaptiveSessionService::class)->gatewayAuthRedirectUrl($session->fresh());
        $this->assertSame('http://192.168.0.1:2060/wifidog/auth?token='.$token, $url);
    }

    private function createRuijieRouter(Company $company, string $gwId, string $status = 'active'): NetworkDevice
    {
        $owner = $company->creator ?? $this->createUser();

        $router = app(RouterService::class)->create($company, [
            'gateway_type' => NetworkDevice::GATEWAY_RUIJIE,
            'name' => 'Gateway '.$gwId,
            'lan_ip' => '192.168.0.1',
            'gateway_id' => $gwId,
            'wifidog_port' => 2060,
            'status' => $status,
        ], $owner);

        if ($status !== 'active') {
            $router->forceFill(['status' => $status])->save();
        }

        return $router->fresh();
    }

    private function sessionTokenFromLocation(string $location): string
    {
        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);

        return (string) ($query['session'] ?? '');
    }
}
