<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Services\AccessExpiryService;
use App\Services\RouterService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccessExpiryTest extends TestCase
{
    public function test_access_expire_command_marks_grant_and_clears_sessions_without_deleting_customer(): void
    {
        [$company, $headers, $customer, $grant, $networkSession, $captive] = $this->seedExpiredAccess();

        Artisan::call('access:expire');

        $this->assertDatabaseHas('access_grants', [
            'id' => $grant->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('network_sessions', [
            'id' => $networkSession->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('captive_sessions', [
            'id' => $captive->id,
            'status' => CaptiveSession::STATUS_EXPIRED,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'active',
            'deleted_at' => null,
        ]);

        $this->withHeaders($headers)->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $customer->id)
            ->assertJsonPath('data.items.0.package', null)
            ->assertJsonPath('data.items.0.access_grant', null)
            ->assertJsonPath('data.items.0.current_session', null)
            ->assertJsonPath('data.items.0.time_left_seconds', null);
    }

    public function test_customer_list_hides_past_due_grant_even_before_cron(): void
    {
        [$company, $headers, $customer] = $this->seedExpiredAccess();

        $this->withHeaders($headers)->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $customer->id)
            ->assertJsonPath('data.items.0.access_grant', null)
            ->assertJsonPath('data.items.0.package', null)
            ->assertJsonPath('data.items.0.current_session', null);
    }

    public function test_wifidog_auth_denies_and_expires_past_due_grant(): void
    {
        [$company, $headers, $customer, $grant, $networkSession, $captive, $router] = $this->seedExpiredAccess();

        $this->get('/api/wifidog/auth?stage=login&token='.$captive->token.'&mac=AA:BB:CC:DD:EE:FF&gw_id='.$router->gateway_id)
            ->assertOk()
            ->assertSee('Auth:0', false);

        $this->assertDatabaseHas('access_grants', [
            'id' => $grant->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('network_sessions', [
            'id' => $networkSession->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('captive_sessions', [
            'id' => $captive->id,
            'status' => CaptiveSession::STATUS_EXPIRED,
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_expire_due_service_is_idempotent(): void
    {
        $this->seedExpiredAccess();

        $first = app(AccessExpiryService::class)->expireDue();
        $second = app(AccessExpiryService::class)->expireDue();

        $this->assertSame(1, $first['grants']);
        $this->assertSame(0, $second['grants']);
    }

    /**
     * @return array{0: \App\Models\Company, 1: array<string, string>, 2: Customer, 3: AccessGrant, 4: NetworkSession, 5: CaptiveSession, 6: NetworkDevice}
     */
    private function seedExpiredAccess(): array
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'expire-cafe']);
        $headers = $this->authHeaders($owner);

        $router = app(RouterService::class)->create($company, [
            'gateway_type' => NetworkDevice::GATEWAY_RUIJIE,
            'name' => 'Expire Gateway',
            'lan_ip' => '192.168.0.144',
            'gateway_id' => 'expire-gw-1',
            'wifidog_port' => 2060,
            'status' => 'active',
        ], $owner)->fresh();

        $plan = InternetPlan::query()->create([
            'company_id' => $company->id,
            'name' => '6 hours',
            'slug' => '6-hours',
            'duration' => 6,
            'duration_unit' => 'HOURS',
            'price' => 500,
            'status' => 'active',
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'name' => 'Juma kassim',
            'phone' => '0687181497',
            'status' => 'active',
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'payment',
            'starts_at' => now()->subHours(7),
            'expires_at' => now()->subHour(),
            'status' => 'active',
        ]);

        $networkSession = NetworkSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'access_grant_id' => $grant->id,
            'internet_plan_id' => $plan->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'session_id' => 'hs-expired-1',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.0.131',
            'started_at' => now()->subHours(7),
            'last_activity_at' => now()->subHours(6),
            'status' => 'active',
        ]);

        $captive = CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.0.131',
            'gw_address' => '192.168.0.144',
            'gw_port' => 2060,
            'token' => str_repeat('e', 32),
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'access_grant_id' => $grant->id,
            'network_session_id' => $networkSession->id,
            'authenticated_at' => now()->subHours(7),
            'expires_at' => now()->subHour(),
        ]);

        return [$company, $headers, $customer, $grant, $networkSession, $captive, $router];
    }
}
