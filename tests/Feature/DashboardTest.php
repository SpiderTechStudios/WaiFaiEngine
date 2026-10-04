<?php

namespace Tests\Feature;

use App\Models\RevenueRecord;
use App\Models\Wallet;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_breaks_revenue_down_by_mobile_money_and_voucher(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'dash-cafe', 'payment_method' => 'both']);
        $headers = $this->authHeaders($owner);

        $routerId = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Dash Router',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data.id');

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => 'Daily',
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => 1500,
        ])->assertCreated()->json('data.id');

        $codes = $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
            'router_id' => $routerId,
            'package_id' => $packageId,
            'quantity' => 3,
        ])->assertCreated()->json('data.items.*.code');

        foreach (array_slice($codes, 0, 2) as $i => $code) {
            $this->postJson('/api/v1/portal/dash-cafe/vouchers/redeem', [
                'code' => $code,
                'customer_name' => 'Guest',
                'customer_phone' => '071100000'.$i,
            ])->assertCreated();
        }

        $wallet = Wallet::query()->create([
            'company_id' => $company->id,
            'currency' => 'TZS',
            'balance' => 1000,
            'status' => 'active',
        ]);

        RevenueRecord::query()->create([
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'source' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'recognized',
            'recognized_at' => now(),
        ]);

        // Revenue from before this month must not leak into today / this month.
        RevenueRecord::query()->create([
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'source' => 'mobile_money',
            'amount' => 5000,
            'currency' => 'TZS',
            'status' => 'recognized',
            'recognized_at' => now()->subMonths(2),
        ]);

        $this->withHeaders($headers)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Africa/Dar_es_Salaam')
            ->assertJsonPath('data.revenue.today.total', 4000)
            ->assertJsonPath('data.revenue.today.mobile_money', 1000)
            ->assertJsonPath('data.revenue.today.voucher', 3000)
            ->assertJsonPath('data.revenue.today.voucher_count', 2)
            ->assertJsonPath('data.revenue.this_month.total', 4000)
            ->assertJsonPath('data.revenue.all_time.total', 9000)
            ->assertJsonPath('data.sales_today.vouchers_sold', 2)
            ->assertJsonPath('data.sales_today.mobile_money_payments', 1)
            ->assertJsonPath('data.vouchers.unused', 1)
            ->assertJsonPath('data.vouchers.sold_this_month', 2)
            ->assertJsonPath('data.customers.new_today', 2)
            ->assertJsonPath('data.wallet.balance', 1000)
            ->assertJsonPath('data.top_packages_this_month.0.name', 'Daily')
            ->assertJsonPath('data.top_packages_this_month.0.sales', 2)
            ->assertJsonPath('data.today_revenue', 4000)
            ->assertJsonPath('data.total_revenue', 9000);

        // Voucher cash is never credited to the withdrawable wallet.
        $this->assertEquals(1000, (float) $wallet->fresh()->balance);
        $this->assertSame(0, RevenueRecord::query()->where('source', 'voucher')->whereNotNull('wallet_id')->count());
    }

    public function test_multi_use_voucher_counts_as_one_sale(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'multi-cafe', 'payment_method' => 'both']);
        $headers = $this->authHeaders($owner);

        $routerId = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Multi Router',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data.id');

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => 'Family',
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => 2000,
        ])->assertCreated()->json('data.id');

        $code = $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
            'router_id' => $routerId,
            'package_id' => $packageId,
            'quantity' => 1,
            'max_uses' => 3,
        ])->assertCreated()->json('data.items.0.code');

        foreach (['0711000001', '0711000002'] as $phone) {
            $this->postJson('/api/v1/portal/multi-cafe/vouchers/redeem', [
                'code' => $code,
                'customer_name' => 'Guest',
                'customer_phone' => $phone,
            ])->assertCreated();
        }

        $this->withHeaders($headers)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue.today.voucher', 2000)
            ->assertJsonPath('data.revenue.today.voucher_count', 1);
    }
}
