<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\PlatformPayment;
use App\Models\RevenueRecord;
use App\Services\RouterService;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    public function test_admin_dashboard_reports_platform_management_stats_only(): void
    {
        config(['platform.subscription_monthly' => 20000]);

        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', [
            'name' => 'Cafe Net',
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->addDays(15),
        ]);

        // A paid platform subscription (what the client pays WaiFai).
        PlatformPayment::query()->create([
            'company_id' => $company->id,
            'type' => PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION,
            'purpose' => PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION,
            'direction' => PlatformPayment::DIRECTION_COLLECTION,
            'reference' => 'PAY-'.strtoupper(Str::random(8)),
            'amount' => 20000,
            'currency' => 'TZS',
            'status' => 'paid',
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        // Client-side hotspot revenue / sessions must NOT drive the admin figures.
        $onlineRouter = app(RouterService::class)->create($company, [
            'gateway_type' => 'mikrotik',
            'name' => 'Online AP',
            'lan_ip' => '192.168.88.1',
            'api_username' => 'admin',
            'api_password' => 'secret',
        ], $owner);
        $onlineRouter->forceFill(['last_seen_at' => now()->subMinutes(2)])->save();

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
            'name' => 'Walk in',
            'phone' => '0754123456',
            'status' => 'active',
        ]);

        $payment = PaymentTransaction::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.strtoupper(Str::random(8)),
            'amount' => 1000,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        RevenueRecord::query()->create([
            'company_id' => $company->id,
            'source' => 'mobile_money',
            'payment_transaction_id' => $payment->id,
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'recognized',
            'recognized_at' => now(),
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'payment_transaction_id' => $payment->id,
            'source' => 'payment',
            'starts_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        NetworkSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'payment_transaction_id' => $payment->id,
            'access_grant_id' => $grant->id,
            'network_device_id' => $onlineRouter->id,
            'session_id' => 'sess-1',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'status' => 'active',
            'started_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.platform.monthly_fee', 20000)
            ->assertJsonPath('data.platform.mrr', 20000)
            ->assertJsonPath('data.platform.collected_this_month', 20000)
            ->assertJsonPath('data.platform.clients.total', 1)
            ->assertJsonPath('data.platform.clients.active', 1)
            ->assertJsonPath('data.platform.clients.new_this_month', 1)
            ->assertJsonPath('data.revenue_statistics.period_days', 14)
            ->assertJsonCount(14, 'data.revenue_statistics.last_14_days');

        // Client-level noise is gone.
        $response->assertJsonMissingPath('data.today_revenue');
        $response->assertJsonMissingPath('data.top_plans');
        $response->assertJsonMissingPath('data.recent_sessions');
    }

    public function test_regular_client_cannot_view_admin_dashboard(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }
}
