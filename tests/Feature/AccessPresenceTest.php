<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Services\CaptiveSessionService;
use App\Services\RouterService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccessPresenceTest extends TestCase
{
    public function test_silent_wifidog_session_keeps_access_until_grant_expiry(): void
    {
        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        $session = NetworkSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'access_grant_id' => $grant->id,
            'internet_plan_id' => $plan->id,
            'network_device_id' => $router->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.0.131',
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(10),
            'status' => 'active',
        ]);

        $captive = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('a', 32),
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'network_session_id' => $session->id,
            'authenticated_at' => now()->subHour(),
            'expires_at' => $grant->expires_at,
            'last_seen_at' => now()->subMinutes(10),
        ]);

        Artisan::call('access:presence');

        $this->assertSame('active', $session->fresh()->status);
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $captive->fresh()->status);
        $this->assertSame('active', $grant->fresh()->status);
        $this->assertTrue($grant->fresh()->isUsable());

        $this->get('/api/wifidog/auth?stage=counters&token='.$captive->token
            .'&gw_id='.$router->gateway_id
            .'&mac=AA:BB:CC:DD:EE:FF'
            .'&incoming=10&outgoing=20')
            ->assertOk()
            ->assertSee('Auth:1');
    }

    public function test_legacy_disconnected_session_with_valid_grant_is_recovered(): void
    {
        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        $captive = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('d', 32),
            'status' => CaptiveSession::STATUS_DISCONNECTED,
            'access_grant_id' => $grant->id,
            'authenticated_at' => now()->subHours(2),
            'expires_at' => $grant->expires_at,
            'last_seen_at' => now()->subHour(),
        ]);

        $allowed = app(CaptiveSessionService::class)->authorizeToken($captive->token, $captive->client_mac);

        $this->assertTrue($allowed);
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $captive->fresh()->status);
        $this->assertDatabaseHas('network_sessions', [
            'access_grant_id' => $grant->id,
            'status' => 'active',
        ]);
    }

    public function test_returning_device_recovers_while_grant_is_still_valid(): void
    {
        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        NetworkSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'access_grant_id' => $grant->id,
            'internet_plan_id' => $plan->id,
            'network_device_id' => $router->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.0.131',
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(10),
            'status' => 'ended',
        ]);

        $returning = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('b', 32),
            'status' => CaptiveSession::STATUS_PENDING,
        ]);

        $url = app(CaptiveSessionService::class)->resumeForReturningDevice($returning);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/wifidog/auth', $url);
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $returning->fresh()->status);
    }

    public function test_returning_device_is_denied_after_grant_expiry_even_if_barely_used(): void
    {
        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        $grant->forceFill([
            'starts_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
            'status' => 'active',
        ])->save();

        $returning = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('c', 32),
            'status' => CaptiveSession::STATUS_PENDING,
        ]);

        $url = app(CaptiveSessionService::class)->resumeForReturningDevice($returning);

        $this->assertNull($url);
        $this->assertSame(CaptiveSession::STATUS_PENDING, $returning->fresh()->status);
        $this->assertFalse($grant->fresh()->isUsable());
    }

    public function test_short_cached_expiry_is_healed_and_session_stays_authorized(): void
    {
        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        $captive = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('f', 32),
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'authenticated_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
            'last_seen_at' => now()->subMinute(),
        ]);

        $this->get('/api/wifidog/auth?stage=counters&token='.$captive->token
            .'&gw_id='.$router->gateway_id
            .'&mac=AA:BB:CC:DD:EE:FF&incoming=10&outgoing=20')
            ->assertOk()
            ->assertSee('Auth:1');

        $refreshed = $captive->fresh();
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $refreshed->status);
        $this->assertTrue($refreshed->expires_at->equalTo($grant->expires_at));
    }

    public function test_cron_does_not_revoke_paid_session_before_grant_expiry(): void
    {
        Queue::fake();

        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        $networkSession = NetworkSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'access_grant_id' => $grant->id,
            'internet_plan_id' => $plan->id,
            'network_device_id' => $router->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.0.131',
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(10),
            'status' => 'active',
        ]);

        $captive = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('e', 32),
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'network_session_id' => $networkSession->id,
            'authenticated_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('access:expire');

        Queue::assertNothingPushed();
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $captive->fresh()->status);
        $this->assertTrue($captive->fresh()->expires_at->equalTo($grant->expires_at));
        $this->assertSame('active', $networkSession->fresh()->status);
        $this->assertSame('active', $grant->fresh()->status);
    }

    public function test_unlimited_grant_is_never_removed_by_cron(): void
    {
        Queue::fake();

        [$company, $router, $customer, $grant, $plan] = $this->fixtures();

        $grant->forceFill(['expires_at' => null, 'status' => 'active'])->save();

        $captive = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => $router->lan_ip,
            'gw_port' => $router->wifidog_port,
            'token' => str_repeat('9', 32),
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'authenticated_at' => now()->subDay(),
            'expires_at' => now()->subDay(),
        ]);

        Artisan::call('access:expire');

        Queue::assertNothingPushed();
        $this->assertSame(CaptiveSession::STATUS_AUTHENTICATED, $captive->fresh()->status);
        $this->assertNull($captive->fresh()->expires_at);
        $this->assertSame('active', $grant->fresh()->status);
    }

    /**
     * @return array{0: Company, 1: NetworkDevice, 2: Customer, 3: AccessGrant, 4: InternetPlan}
     */
    private function fixtures(): array
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $router = app(RouterService::class)->create($company, [
            'gateway_type' => NetworkDevice::GATEWAY_RUIJIE,
            'name' => 'Presence Gateway',
            'lan_ip' => '192.168.0.144',
            'gateway_id' => 'presence-gw-1',
            'wifidog_port' => 2060,
            'status' => 'active',
        ], $owner)->fresh();

        $plan = InternetPlan::query()->create([
            'company_id' => $company->id,
            'name' => '24 hours',
            'slug' => '24-hours-'.strtolower(str()->random(4)),
            'duration' => 24,
            'duration_unit' => 'HOURS',
            'price' => 1000,
            'status' => 'active',
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'name' => 'Asha Mohamed',
            'phone' => '0754000000',
            'status' => 'active',
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'payment',
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHours(23),
            'status' => 'active',
        ]);

        return [$company, $router, $customer, $grant, $plan];
    }
}
