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
use Tests\TestCase;

class AccessPresenceTest extends TestCase
{
    public function test_silent_wifidog_session_is_marked_offline_but_keeps_the_grant(): void
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

        $this->assertSame('ended', $session->fresh()->status);
        $this->assertSame(CaptiveSession::STATUS_DISCONNECTED, $captive->fresh()->status);
        $this->assertSame('active', $grant->fresh()->status);
        $this->assertTrue($grant->fresh()->isUsable());
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
