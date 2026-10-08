<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\RevenueRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouterApiTest extends TestCase
{
    private User $owner;

    private Company $company;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-10-08 12:00 EAT
        Carbon::setTestNow(Carbon::parse('2026-10-08 09:00:00', 'UTC'));

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', [
            'subdomain' => 'router-cafe',
            'payment_method' => 'both',
        ]);
        $this->headers = $this->authHeaders($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summary_counts_all_routers_with_live_clients_and_today_revenue(): void
    {
        $online = $this->router('Main AP');
        $online->forceFill(['last_seen_at' => now()->subMinute()])->save();

        $offline = $this->router('Back AP');
        $offline->forceFill(['last_seen_at' => now()->subHours(3)])->save();

        $this->sale($this->customer('0754000001'), $this->plan(1500), 1500, $online, now()->subMinutes(30));

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/routers/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.online', 1)
            ->assertJsonPath('data.offline', 1)
            ->assertJsonPath('data.clients_now', 1)
            ->assertJsonPath('data.revenue_today', 1500)
            ->assertJsonPath('data.currency', 'TZS');
    }

    public function test_router_list_rows_include_revenue_and_clients(): void
    {
        $router = $this->router('Main AP');
        $router->forceFill(['last_seen_at' => now()->subMinute()])->save();

        $this->sale($this->customer('0754000002'), $this->plan(2000), 2000, $router, now()->subMinutes(10));

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/routers')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $router->id)
            ->assertJsonPath('data.items.0.revenue_today', 2000)
            ->assertJsonPath('data.items.0.payments_today', 1)
            ->assertJsonPath('data.items.0.clients_now', 1);
    }

    public function test_router_show_includes_live_stats_and_identity(): void
    {
        $router = $this->router('Main AP');
        $router->forceFill([
            'last_seen_at' => now()->subMinute(),
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'firmware' => '7.1.5',
            'model' => 'RB750Gr3',
        ])->save();

        $this->sale($this->customer('0754000003'), $this->plan(1000), 1000, $router, now()->subMinutes(20));

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/routers/'.$router->id)
            ->assertOk()
            ->assertJsonPath('data.clients_now', 1)
            ->assertJsonPath('data.revenue.today', 1000)
            ->assertJsonPath('data.revenue.week', 1000)
            ->assertJsonPath('data.revenue.month', 1000)
            ->assertJsonPath('data.revenue.currency', 'TZS')
            ->assertJsonPath('data.firmware', '7.1.5')
            ->assertJsonPath('data.model', 'RB750Gr3')
            ->assertJsonPath('data.mac_address', 'AA:BB:CC:DD:EE:FF');
    }

    public function test_router_events_include_audit_and_offline_event(): void
    {
        $router = $this->router('Main AP');
        $router->forceFill(['last_seen_at' => now()->subHours(2)])->save();

        $events = $this->withHeaders($this->headers)
            ->getJson('/api/v1/routers/'.$router->id.'/events')
            ->assertOk()
            ->json('data');

        $types = array_column($events, 'type');
        $this->assertContains('created', $types);
        $this->assertContains('offline', $types);

        $created = collect($events)->firstWhere('type', 'created');
        $this->assertSame(
            trim($this->owner->first_name.' '.$this->owner->last_name),
            $created['by'],
        );

        $offline = collect($events)->firstWhere('type', 'offline');
        $this->assertTrue($offline['ongoing']);
        $this->assertGreaterThan(0, $offline['duration_seconds']);
    }

    public function test_router_setup_returns_portal_url_and_commands(): void
    {
        $router = $this->router('Main AP');

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/routers/'.$router->id.'/setup')
            ->assertOk()
            ->assertJsonPath('data.type', 'mikrotik');

        $this->assertStringContainsString('router-cafe', $response->json('data.portal_url'));
        $this->assertNotEmpty($response->json('data.commands'));
        $this->assertNotEmpty($response->json('data.steps'));
    }

    public function test_router_test_rejects_router_without_address(): void
    {
        $router = $this->router('Wavlink AP', 'wavlink', ['lan_ip' => null]);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/routers/'.$router->id.'/test')
            ->assertStatus(422)
            ->assertJsonPath('message', 'This router has no address to test.');
    }

    public function test_router_test_uses_ruijie_cloud_for_ruijie_routers(): void
    {
        $this->company->forceFill([
            'ruijie_account_id' => 'ruijie-user',
            'ruijie_password' => Crypt::encryptString('ruijie-pass'),
        ])->save();

        config(['services.ruijie.base_url' => 'https://ruijie.test']);

        Http::fake([
            'https://ruijie.test/devices/G1TEST0001' => Http::response([
                'serial_number' => 'SN-1',
                'online' => true,
                'firmware' => '6.49.17',
                'model' => 'EG105G-P',
            ]),
        ]);

        $router = $this->router('Ruijie AP', 'ruijie');

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/routers/'.$router->id.'/test')
            ->assertOk()
            ->assertJsonPath('data.reachable', true)
            ->assertJsonPath('data.online', true)
            ->assertJsonPath('data.firmware', '6.49.17')
            ->assertJsonPath('data.model', 'EG105G-P');
    }

    public function test_reboot_rejects_non_mikrotik_router(): void
    {
        $router = $this->router('Wavlink AP', 'wavlink');

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/routers/'.$router->id.'/reboot')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Reboot is currently supported for MikroTik routers only.');
    }

    public function test_reboot_sends_rest_command_for_mikrotik(): void
    {
        Http::fake([
            '*/rest/system/reboot' => Http::response('', 204),
        ]);

        $router = $this->router('Main AP');

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/routers/'.$router->id.'/reboot')
            ->assertOk()
            ->assertJsonPath('data.requested', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'router_rebooted',
            'entity_id' => $router->id,
        ]);
    }

    public function test_other_company_cannot_access_router_endpoints(): void
    {
        $router = $this->router('Main AP');

        $other = $this->createUser();
        $this->createCompanyFor($other);
        $otherHeaders = $this->authHeaders($other);

        $this->withHeaders($otherHeaders)->getJson('/api/v1/routers/'.$router->id)->assertNotFound();
        $this->withHeaders($otherHeaders)->getJson('/api/v1/routers/'.$router->id.'/events')->assertNotFound();
        $this->withHeaders($otherHeaders)->getJson('/api/v1/routers/'.$router->id.'/setup')->assertNotFound();
        $this->withHeaders($otherHeaders)->postJson('/api/v1/routers/'.$router->id.'/test')->assertNotFound();
        $this->withHeaders($otherHeaders)->postJson('/api/v1/routers/'.$router->id.'/reboot')->assertNotFound();
    }

    private function router(string $name, string $gateway = 'mikrotik', array $overrides = []): NetworkDevice
    {
        $payload = array_merge(match ($gateway) {
            'ruijie' => [
                'gateway_type' => 'ruijie',
                'name' => $name,
                'lan_ip' => '192.168.88.1',
                'gateway_id' => 'G1TEST0001',
                'wifidog_port' => 2060,
            ],
            'wavlink' => [
                'gateway_type' => 'wavlink',
                'name' => $name,
                'lan_ip' => '192.168.10.1',
            ],
            default => [
                'gateway_type' => 'mikrotik',
                'name' => $name,
                'lan_ip' => '192.168.88.1',
                'api_host' => 'router.test',
                'api_port' => 443,
                'api_username' => 'admin',
                'api_password' => 'secret-pass',
            ],
        }, $overrides);

        $id = $this->withHeaders($this->headers)
            ->postJson('/api/v1/routers', $payload)
            ->assertCreated()
            ->json('data.id');

        return NetworkDevice::query()->findOrFail($id);
    }

    private function plan(int $price): InternetPlan
    {
        $id = $this->withHeaders($this->headers)->postJson('/api/v1/packages', [
            'name' => 'Plan '.$price,
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => $price,
        ])->assertCreated()->json('data.id');

        return InternetPlan::query()->findOrFail($id);
    }

    private function customer(string $phone): Customer
    {
        return Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Customer '.$phone,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    private function sale(
        Customer $customer,
        InternetPlan $plan,
        int $amount,
        NetworkDevice $router,
        Carbon $paidAt,
    ): void {
        $payment = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'initiated_at' => $paidAt,
            'paid_at' => $paidAt,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);

        RevenueRecord::query()->create([
            'company_id' => $this->company->id,
            'source' => 'mobile_money',
            'payment_transaction_id' => $payment->id,
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'recognized',
            'recognized_at' => $paidAt,
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'payment_transaction_id' => $payment->id,
            'source' => 'mobile_money',
            'starts_at' => $paidAt,
            'expires_at' => $paidAt->copy()->addDay(),
            'status' => 'active',
        ]);

        NetworkSession::query()->create([
            'company_id' => $this->company->id,
            'access_grant_id' => $grant->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'payment_transaction_id' => $payment->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'mac_address' => 'AA:BB:CC:00:00:'.str_pad((string) ($payment->id % 100), 2, '0', STR_PAD_LEFT),
            'started_at' => $paidAt,
            'status' => 'active',
            'upload_bytes' => 0,
            'download_bytes' => 0,
        ]);
    }
}
