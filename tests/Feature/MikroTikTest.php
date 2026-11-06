<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\User;
use App\Services\CaptiveSessionService;
use App\Services\RouterService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MikroTikTest extends TestCase
{
    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://waifai.test',
            'captive.force_backend_portal' => true,
            'captive.portal_connect_path' => '/connect',
            'mikrotik.verify_tls' => false,
        ]);

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', [
            'subdomain' => 'mt-cafe',
            'payment_method' => 'both',
        ]);
    }

    public function test_login_creates_pending_captive_session_and_redirects_to_connect(): void
    {
        $router = $this->mikroTikRouter();

        $response = $this->get('/api/mikrotik/login?'.http_build_query([
            'router' => $router->id,
            'key' => $router->portal_key,
            'mac' => 'AA:BB:CC:DD:EE:FF',
            'ip' => '192.168.88.50',
            'url' => 'https://example.com/start',
        ]));

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://waifai.test/connect?', $location);
        $this->assertStringContainsString('subdomain=mt-cafe', $location);
        $this->assertMatchesRegularExpression('/session=[a-f0-9]{32}/', $location);

        $this->assertDatabaseHas('captive_sessions', [
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.88.50',
            'gw_address' => '192.168.88.1',
            'gw_port' => 80,
            'status' => CaptiveSession::STATUS_PENDING,
        ]);

        $this->assertNotNull($router->fresh()->last_seen_at);
    }

    public function test_login_rejects_an_invalid_portal_key(): void
    {
        $router = $this->mikroTikRouter();

        $this->get('/api/mikrotik/login?'.http_build_query([
            'router' => $router->id,
            'key' => 'wrong-key',
            'mac' => 'AA:BB:CC:DD:EE:FF',
        ]))->assertForbidden();

        $this->assertDatabaseCount('captive_sessions', 0);
    }

    public function test_gateway_page_provisions_a_one_time_hotspot_user_and_auto_submits(): void
    {
        $router = $this->mikroTikRouter();
        $session = $this->authenticatedSession($router);

        Http::fake([
            '*rest/ip/hotspot/active' => Http::response([], 200),
            '*rest/ip/hotspot/user/profile/add' => Http::response(['.id' => '*P1'], 201),
            '*rest/ip/hotspot/user/profile' => Http::response([], 200),
            '*rest/ip/hotspot/user/add' => Http::response(['.id' => '*U1'], 201),
            '*rest/ip/hotspot/user' => Http::response([], 200),
            '*rest/ip/hotspot/cookie' => Http::response([], 200),
        ]);

        $response = $this->get('/gateway/mikrotik/'.$session->token);

        $response->assertOk();
        $response->assertSee('action="http://192.168.88.1:80/login"', false);
        $response->assertSee('value="wf_'.$session->token.'"', false);
        $response->assertSee('name="dst"', false);

        $session->refresh();
        $this->assertSame('wf_'.$session->token, $session->mikrotik_username);
        $this->assertNotNull($session->mikrotik_provisioned_at);
        $this->assertNotNull($session->mikrotik_password);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/ip/hotspot/user/add')) {
                return false;
            }

            return $request['limit-bytes-total'] === '524288000'
                && $request['limit-uptime'] !== null
                && $request['profile'] === 'wf-'.$this->company->id.'-'.$this->plan()->id;
        });

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/ip/hotspot/user/profile/add')
                && $request['rate-limit'] === '10M/5M';
        });
    }

    public function test_admin_can_list_and_remove_a_mikrotik_client(): void
    {
        $router = $this->mikroTikRouter();
        $session = $this->authenticatedSession($router);
        $session->forceFill([
            'mikrotik_username' => 'wf_'.$session->token,
            'mikrotik_provisioned_at' => now(),
        ])->save();

        $activeRow = [
            '.id' => '*A1',
            'user' => 'wf_'.$session->token,
            'mac-address' => 'AA:BB:CC:DD:EE:FF',
            'address' => '192.168.88.50',
            'uptime' => '1h2m',
            'bytes-in' => '100',
            'bytes-out' => '200',
            'comment' => 'wf|'.$this->company->id.'|'.$session->id.'|unlimited',
        ];

        Http::fake([
            '*rest/ip/hotspot/active' => Http::response([$activeRow], 200),
            '*rest/ip/hotspot/active/*A1' => Http::response('', 204),
            '*rest/ip/hotspot/user/*U1' => Http::response('', 204),
            '*rest/ip/hotspot/user' => Http::response([[
                '.id' => '*U1',
                'name' => 'wf_'.$session->token,
                'comment' => 'wf|'.$this->company->id.'|'.$session->id.'|unlimited',
            ]], 200),
            '*rest/ip/hotspot/cookie' => Http::response([], 200),
        ]);

        $headers = $this->authHeaders($this->owner);

        $this->withHeaders($headers)
            ->getJson('/api/v1/routers/'.$router->id.'/clients')
            ->assertOk()
            ->assertJsonPath('data.0.user', 'wf_'.$session->token)
            ->assertJsonPath('data.0.mac', 'AA:BB:CC:DD:EE:FF')
            ->assertJsonPath('data.0.captive_session_id', $session->id);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/routers/'.$router->id.'/clients/AA:BB:CC:DD:EE:FF')
            ->assertOk()
            ->assertJsonPath('data.active', 1)
            ->assertJsonPath('data.users', 1);

        $this->assertSame(CaptiveSession::STATUS_DISCONNECTED, $session->fresh()->status);
        $this->assertNull($session->fresh()->mikrotik_username);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'router_client_removed',
            'entity_id' => $router->id,
        ]);
    }

    public function test_router_logout_callback_ends_the_network_session(): void
    {
        $router = $this->mikroTikRouter();
        $session = $this->authenticatedSession($router, withNetworkSession: true);
        $session->forceFill(['mikrotik_username' => 'wf_'.$session->token])->save();

        $secret = Crypt::decryptString((string) $router->callback_secret);

        $this->postJson('/api/mikrotik/callback', [
            'router' => $router->id,
            'secret' => $secret,
            'event' => 'logout',
            'user' => 'wf_'.$session->token,
            'bytes-in' => 1234,
            'bytes-out' => 5678,
        ])->assertNoContent();

        $this->assertSame(CaptiveSession::STATUS_DISCONNECTED, $session->fresh()->status);
        $this->assertDatabaseHas('network_sessions', [
            'id' => $session->network_session_id,
            'status' => 'ended',
            'upload_bytes' => 1234,
            'download_bytes' => 5678,
        ]);
    }

    public function test_sync_command_updates_router_heartbeat_and_usage(): void
    {
        $router = $this->mikroTikRouter();
        $session = $this->authenticatedSession($router, withNetworkSession: true);
        $session->forceFill(['mikrotik_username' => 'wf_'.$session->token])->save();

        Http::fake([
            '*rest/system/resource' => Http::response(['version' => '7.15.3', 'uptime' => '1d2h'], 200),
            '*rest/ip/hotspot/active' => Http::response([[
                '.id' => '*A1',
                'user' => 'wf_'.$session->token,
                'mac-address' => 'AA:BB:CC:DD:EE:FF',
                'bytes-in' => '111',
                'bytes-out' => '222',
            ]], 200),
            '*rest/ip/hotspot/user' => Http::response([], 200),
        ]);

        $this->artisan('mikrotik:sync', ['--router' => $router->id])
            ->assertExitCode(0);

        $router->refresh();
        $this->assertNotNull($router->last_seen_at);
        $this->assertNotNull($router->last_synced_at);
        $this->assertSame('7.15.3', $router->firmware);

        $this->assertSame('*A1', $session->fresh()->router_active_id);
        $this->assertNotNull($session->fresh()->router_authorized_at);
        $this->assertDatabaseHas('network_sessions', [
            'id' => $session->network_session_id,
            'upload_bytes' => 111,
            'download_bytes' => 222,
        ]);
    }

    public function test_sync_keeps_hotspot_user_while_the_grant_is_still_usable(): void
    {
        $router = $this->mikroTikRouter();
        $session = $this->authenticatedSession($router, withNetworkSession: true);

        $session->forceFill([
            'mikrotik_username' => 'wf_'.$session->token,
            'mikrotik_provisioned_at' => now(),
            'router_last_seen_at' => now()->subMinutes(10),
        ])->save();

        Http::fake([
            '*rest/system/resource' => Http::response(['version' => '7.15.3', 'uptime' => '1d2h'], 200),
            '*rest/ip/hotspot/active' => Http::response([], 200),
            '*rest/ip/hotspot/user' => Http::response([[
                '.id' => '*U1',
                'name' => 'wf_'.$session->token,
                'comment' => 'wf|'.$this->company->id.'|'.$session->id.'|unlimited',
            ]], 200),
            '*rest/ip/hotspot/cookie' => Http::response([], 200),
        ]);

        $this->artisan('mikrotik:sync', ['--router' => $router->id])
            ->assertExitCode(0);

        $session->refresh();
        $this->assertSame(CaptiveSession::STATUS_DISCONNECTED, $session->status);
        $this->assertSame('wf_'.$session->token, $session->mikrotik_username);

        Http::assertNotSent(function ($request): bool {
            return $request->method() === 'DELETE'
                && (str_contains($request->url(), '/ip/hotspot/user') || str_contains($request->url(), '/ip/hotspot/cookie'));
        });
    }

    public function test_returning_device_resumes_from_a_usable_grant_after_network_session_ended(): void
    {
        $router = $this->mikroTikRouter();
        $plan = $this->plan();

        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Voucher Guest',
            'phone' => '0711000001',
            'status' => 'active',
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'voucher',
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addDays(3),
            'status' => 'active',
        ]);

        $ended = NetworkSession::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'access_grant_id' => $grant->id,
            'internet_plan_id' => $plan->id,
            'network_device_id' => $router->id,
            'session_id' => str_repeat('c', 32),
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(30),
            'status' => 'ended',
            'ended_at' => now()->subMinutes(30),
        ]);

        $pending = CaptiveSession::query()->create([
            'company_id' => $this->company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.88.50',
            'gw_address' => '192.168.88.1',
            'gw_port' => 80,
            'token' => str_repeat('b', 32),
            'status' => CaptiveSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $url = app(CaptiveSessionService::class)->resumeForReturningDevice($pending);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/gateway/mikrotik/'.$pending->token, $url);

        $pending->refresh();
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $pending->status);
        $this->assertSame($grant->id, $pending->access_grant_id);
        $this->assertNotNull($pending->network_session_id);
        $this->assertSame('ended', $ended->fresh()->status);

        $this->assertDatabaseHas('network_sessions', [
            'id' => $pending->network_session_id,
            'access_grant_id' => $grant->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'status' => 'active',
        ]);
    }

    private function mikroTikRouter(): NetworkDevice
    {
        return app(RouterService::class)->create($this->company, [
            'gateway_type' => NetworkDevice::GATEWAY_MIKROTIK,
            'name' => 'MikroTik '.uniqid(),
            'lan_ip' => '192.168.88.1',
            'api_host' => 'router.test',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
            'hotspot_port' => 80,
        ], $this->owner)->fresh();
    }

    private function plan(): InternetPlan
    {
        return InternetPlan::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'hourly-10m'],
            [
                'name' => 'Hourly 10Mbps',
                'duration' => 1,
                'duration_unit' => 'HOURS',
                'price' => 1000,
                'speed_download_mbps' => 10,
                'speed_upload_mbps' => 5,
                'data_cap_mb' => 500,
                'status' => 'active',
                'visible_on_portal' => true,
            ],
        );
    }

    private function authenticatedSession(NetworkDevice $router, bool $withNetworkSession = false): CaptiveSession
    {
        $plan = $this->plan();

        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Guest',
            'phone' => '0711000000',
            'status' => 'active',
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'payment',
            'starts_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $token = str_repeat('a', 32);
        $networkSession = null;

        if ($withNetworkSession) {
            $networkSession = NetworkSession::query()->create([
                'company_id' => $this->company->id,
                'customer_id' => $customer->id,
                'access_grant_id' => $grant->id,
                'internet_plan_id' => $plan->id,
                'network_device_id' => $router->id,
                'network_station_id' => $router->network_station_id,
                'session_id' => $token,
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'started_at' => now(),
                'last_activity_at' => now(),
                'status' => 'active',
            ]);
        }

        return CaptiveSession::query()->create([
            'company_id' => $this->company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.88.50',
            'gw_address' => '192.168.88.1',
            'gw_port' => 80,
            'requested_url' => 'https://example.com/',
            'token' => $token,
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'network_session_id' => $networkSession?->id,
            'authenticated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }
}
