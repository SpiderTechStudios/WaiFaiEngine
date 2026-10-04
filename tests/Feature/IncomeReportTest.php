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
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncomeReportTest extends TestCase
{
    private User $owner;

    private Company $company;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-09-30 12:00 EAT
        Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00', 'UTC'));

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', ['subdomain' => 'income-cafe', 'payment_method' => 'both']);
        $this->headers = $this->authHeaders($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_to_last_14_days_with_continuous_daily_rows_and_legacy_fields(): void
    {
        $response = $this->withHeaders($this->headers)->getJson('/api/v1/income')->assertOk();

        $response->assertJsonPath('data.range.from', '2026-09-17')
            ->assertJsonPath('data.range.to', '2026-09-30')
            ->assertJsonPath('data.range.timezone', 'Africa/Dar_es_Salaam')
            ->assertJsonPath('data.totals.gross', 0)
            ->assertJsonPath('data.counts.avg_ticket', 0)
            ->assertJsonPath('data.customers.new', 0)
            ->assertJsonCount(14, 'data.daily')
            ->assertJsonCount(24, 'data.by_hour')
            ->assertJsonPath('data.by_provider', [])
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.by_source.voucher', 0)
            ->assertJsonPath('data.last_14_days', []);

        $this->assertArrayNotHasKey('previous', $response->json('data'));
    }

    public function test_breaks_down_mobile_money_and_vouchers_and_matches_dashboard(): void
    {
        [$router, $plan] = $this->routerAndPlan('Daily', 1500);
        $customer = $this->customer('0754000001');

        $this->mobileMoneySale($customer, $plan, 1500, 'Mpesa', '2026-09-30 05:00:00', $router);
        $this->mobileMoneySale($customer, $plan, 1000, 'MixxByYas', '2026-09-29 10:00:00');
        $this->redeemVoucher($router, $plan, '0711000009');

        $this->mobileMoneyAttempt($plan, 'pending');
        $this->mobileMoneyAttempt($plan, 'failed');

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.totals.gross', 4000)
            ->assertJsonPath('data.totals.mobile_money', 2500)
            ->assertJsonPath('data.totals.vouchers', 1500)
            ->assertJsonPath('data.totals.fees', 0)
            ->assertJsonPath('data.totals.net', 4000)
            ->assertJsonPath('data.totals.pending', 1500)
            ->assertJsonPath('data.counts.paid', 3)
            ->assertJsonPath('data.counts.mobile_money_paid', 2)
            ->assertJsonPath('data.counts.vouchers_sold', 1)
            ->assertJsonPath('data.counts.pending', 1)
            ->assertJsonPath('data.counts.failed', 1)
            ->assertJsonPath('data.counts.initiated', 4)
            ->assertJsonPath('data.counts.avg_ticket', 1333)
            ->assertJsonPath('data.by_package.0.name', 'Daily')
            ->assertJsonPath('data.by_package.0.vouchers', 1500)
            ->assertJsonPath('data.by_router.0.router_id', $router->id)
            ->assertJsonPath('data.customers.new', 2)
            ->assertJsonPath('data.vouchers.used', 1)
            ->assertJsonCount(30, 'data.daily');

        $data = $response->json('data');
        $this->assertEquals($data['totals']['gross'], array_sum(array_column($data['daily'], 'total')));
        $this->assertEqualsCanonicalizing(
            [['provider' => 'mpesa', 'amount' => 1500, 'count' => 1], ['provider' => 'mixx', 'amount' => 1000, 'count' => 1]],
            $data['by_provider'],
        );
        $this->assertSame(2, array_sum(array_column($data['by_router'], 'count')));
        $this->assertEquals(1500, $data['by_hour'][8]['amount']); // 05:00 UTC = 08:00 EAT

        $this->withHeaders($this->headers)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue.this_month.total', $data['totals']['gross'])
            ->assertJsonPath('data.revenue.this_month.mobile_money', $data['totals']['mobile_money'])
            ->assertJsonPath('data.revenue.this_month.voucher', $data['totals']['vouchers']);
    }

    public function test_late_evening_payment_lands_on_local_day(): void
    {
        [, $plan] = $this->routerAndPlan('1 Hour', 500);

        // 20:30 UTC on the 28th = 23:30 EAT on the 28th
        $this->mobileMoneySale($this->customer('0754000002'), $plan, 500, 'Mpesa', '2026-09-28 20:30:00');
        // 21:30 UTC on the 28th = 00:30 EAT on the 29th
        $this->mobileMoneySale($this->customer('0754000003'), $plan, 700, 'Mpesa', '2026-09-28 21:30:00');

        $daily = collect($this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-09-28&to=2026-09-29')
            ->assertOk()
            ->json('data.daily'))->keyBy('date');

        $this->assertEquals(500, $daily['2026-09-28']['total']);
        $this->assertEquals(700, $daily['2026-09-29']['total']);
    }

    public function test_compare_returns_previous_period_of_same_length(): void
    {
        [, $plan] = $this->routerAndPlan('1 Hour', 500);
        $customer = $this->customer('0754000004');

        $this->mobileMoneySale($customer, $plan, 800, 'Mpesa', '2026-08-15 10:00:00');
        $this->mobileMoneySale($customer, $plan, 500, 'Mpesa', '2026-09-10 10:00:00');

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-09-01&to=2026-09-30&compare=1')
            ->assertOk()
            ->assertJsonPath('data.previous.from', '2026-08-02')
            ->assertJsonPath('data.previous.to', '2026-08-31')
            ->assertJsonPath('data.previous.gross', 800)
            ->assertJsonPath('data.previous.paid', 1)
            ->assertJsonCount(30, 'data.previous.daily')
            ->assertJsonPath('data.customers.new', 0)
            ->assertJsonPath('data.customers.returning', 1);
    }

    public function test_router_filter_limits_range_data_but_not_wallet(): void
    {
        [$routerA, $plan] = $this->routerAndPlan('Daily', 1500);
        $routerB = $this->router('Second AP');

        $this->mobileMoneySale($this->customer('0754000005'), $plan, 1500, 'Mpesa', '2026-09-30 05:00:00', $routerA);
        $this->mobileMoneySale($this->customer('0754000006'), $plan, 1000, 'Mpesa', '2026-09-30 06:00:00', $routerB);

        Wallet::query()->updateOrCreate(
            ['company_id' => $this->company->id, 'currency' => 'TZS'],
            ['balance' => 2500, 'status' => 'active'],
        );

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-09-30&to=2026-09-30&router_id='.$routerB->id)
            ->assertOk()
            ->assertJsonPath('data.totals.gross', 1000)
            ->assertJsonPath('data.counts.paid', 1)
            ->assertJsonPath('data.totals.available_balance', 2500);
    }

    public function test_paid_out_counts_completed_withdrawals_in_range(): void
    {
        $wallet = Wallet::query()->create(['company_id' => $this->company->id, 'currency' => 'TZS', 'balance' => 0, 'status' => 'active']);

        foreach ([['completed', '2026-09-20 10:00:00', 3000], ['completed', '2026-08-20 10:00:00', 9000], ['pending', '2026-09-21 10:00:00', 400]] as [$status, $at, $amount]) {
            Withdrawal::query()->create([
                'company_id' => $this->company->id,
                'wallet_id' => $wallet->id,
                'provider' => 'mpesa',
                'destination_phone' => '0754000000',
                'reference' => 'WD-'.Str::upper(Str::random(8)),
                'amount' => $amount,
                'currency' => 'TZS',
                'status' => $status,
                'requested_at' => $at,
                'processed_at' => $status === 'completed' ? $at : null,
            ]);
        }

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.totals.paid_out', 3000)
            ->assertJsonPath('data.totals.pending_withdrawals', 400);
    }

    public function test_invalid_ranges_and_foreign_filters_are_rejected(): void
    {
        $this->withHeaders($this->headers)->getJson('/api/v1/income?from=2026-09-10&to=2026-09-01')
            ->assertStatus(422)->assertJsonStructure(['data' => ['to']]);

        $this->withHeaders($this->headers)->getJson('/api/v1/income?from=2025-01-01&to=2026-09-30')
            ->assertStatus(422)->assertJsonStructure(['data' => ['to']]);

        $this->withHeaders($this->headers)->getJson('/api/v1/income?to=2026-10-01')
            ->assertStatus(422)->assertJsonStructure(['data' => ['to']]);

        $this->withHeaders($this->headers)->getJson('/api/v1/income?from=30-09-2026')
            ->assertStatus(422)->assertJsonStructure(['data' => ['from']]);

        $otherOwner = $this->createUser();
        $this->createCompanyFor($otherOwner, 'owner', ['subdomain' => 'other-cafe']);
        $otherRouterId = $this->withHeaders($this->authHeaders($otherOwner))->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Other',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data.id');

        $this->withHeaders($this->headers)->getJson('/api/v1/income?router_id='.$otherRouterId)
            ->assertStatus(422)->assertJsonStructure(['data' => ['router_id']]);
    }

    public function test_csv_and_pdf_export(): void
    {
        [$router, $plan] = $this->routerAndPlan('Daily', 1500);
        $this->mobileMoneySale($this->customer('0754000007'), $plan, 1500, 'Mpesa', '2026-09-29 05:00:00', $router);

        $csv = $this->withHeaders($this->headers)
            ->get('/api/v1/income/export?from=2026-09-28&to=2026-09-30&format=csv')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=income-2026-09-28_2026-09-30.csv')
            ->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertSame('date,mobile_money,vouchers,total,transactions', $lines[0]);
        $this->assertCount(5, $lines);
        $this->assertSame('TOTAL,1500,0,1500,1', $lines[4]);

        $pdf = $this->withHeaders($this->headers)
            ->get('/api/v1/income/export?from=2026-09-28&to=2026-09-30&format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->withHeaders($this->headers)->getJson('/api/v1/income/export?format=xls')
            ->assertStatus(422);
    }

    /**
     * @return array{0: NetworkDevice, 1: InternetPlan}
     */
    private function routerAndPlan(string $planName, int $price): array
    {
        $router = $this->router('Main AP');

        $planId = $this->withHeaders($this->headers)->postJson('/api/v1/packages', [
            'name' => $planName,
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => $price,
        ])->assertCreated()->json('data.id');

        return [$router, InternetPlan::query()->findOrFail($planId)];
    }

    private function router(string $name): NetworkDevice
    {
        $id = $this->withHeaders($this->headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => $name,
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data.id');

        return NetworkDevice::query()->findOrFail($id);
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

    private function mobileMoneySale(Customer $customer, InternetPlan $plan, int $amount, string $network, string $paidAtUtc, ?NetworkDevice $router = null): void
    {
        $paidAt = Carbon::parse($paidAtUtc, 'UTC');

        $payment = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'currency' => 'TZS',
            'payment_method' => $network,
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

        if ($router) {
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
                'mac_address' => 'AA:BB:CC:00:00:'.str_pad((string) ($payment->id % 100), 2, '0', STR_PAD_LEFT),
                'started_at' => $paidAt,
                'status' => 'active',
                'upload_bytes' => 0,
                'download_bytes' => 0,
            ]);
        }
    }

    private function mobileMoneyAttempt(InternetPlan $plan, string $status): void
    {
        PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $plan->price,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => $status,
            'initiated_at' => now(),
        ]);
    }

    private function redeemVoucher(NetworkDevice $router, InternetPlan $plan, string $phone): void
    {
        $code = $this->withHeaders($this->headers)->postJson('/api/v1/vouchers', [
            'router_id' => $router->id,
            'package_id' => $plan->id,
            'quantity' => 1,
        ])->assertCreated()->json('data.items.0.code');

        $this->postJson('/api/v1/portal/income-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Guest',
            'customer_phone' => $phone,
        ])->assertCreated();
    }
}
