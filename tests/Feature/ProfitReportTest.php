<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\PlatformPayment;
use App\Models\RevenueRecord;
use App\Models\User;
use App\Services\PlatformPaymentService;
use App\Services\RouterService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfitReportTest extends TestCase
{
    private User $owner;

    private Company $company;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-08 09:00:00', 'UTC'));

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', [
            'subdomain' => 'profit-cafe',
            'timezone' => 'Africa/Dar_es_Salaam',
        ]);
        $this->headers = $this->authHeaders($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_income_report_includes_expenses_and_profit(): void
    {
        $router = $this->router('Main AP');
        $this->sale(1500, now(), $router);
        $this->expense(500, now()->toDateString(), 'operational', $router);

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-10-08&to=2026-10-08')
            ->assertOk()
            ->json('data');

        $this->assertSame(1500.0, (float) $data['totals']['gross']);
        $this->assertSame(500.0, (float) $data['expenses']['total']);
        $this->assertSame(500.0, (float) $data['expenses']['operational']);
        $this->assertSame(1000.0, (float) $data['profit']['net']);
        $this->assertSame(66.67, (float) $data['profit']['margin']);
        $this->assertSame(1000.0, (float) $data['profit']['net_excluding_platform']);

        // Daily series carries costs and net too.
        $this->assertSame(500.0, (float) $data['daily'][0]['expenses']);
        $this->assertSame(1000.0, (float) $data['daily'][0]['net']);

        // Per-router profitability.
        $row = collect($data['by_router'])->firstWhere('router_id', $router->id);
        $this->assertNotNull($row);
        $this->assertSame(1500.0, (float) $row['amount']);
        $this->assertSame(500.0, (float) $row['expenses']);
        $this->assertSame(1000.0, (float) $row['net']);
    }

    public function test_profit_only_counts_the_platform_currency(): void
    {
        $this->sale(1000, now(), null, 'TZS');
        $this->sale(100, now(), null, 'USD');
        $this->expense(300, now()->toDateString(), 'operational', null, 'TZS');
        $this->expense(50, now()->toDateString(), 'operational', null, 'USD');

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-10-08&to=2026-10-08')
            ->assertOk()
            ->json('data');

        $this->assertSame(1000.0, (float) $data['totals']['gross']);
        $this->assertSame(300.0, (float) $data['expenses']['total']);
        $this->assertSame(700.0, (float) $data['profit']['net']);
        // Other-currency costs are surfaced, not silently added.
        $this->assertNotEmpty($data['expenses']['by_currency']);
    }

    public function test_compare_includes_previous_expenses_and_profit(): void
    {
        $this->sale(1000, now(), null);
        $this->expense(200, now()->toDateString(), 'operational');
        $this->sale(800, now()->subDay(), null);
        $this->expense(100, now()->subDay()->toDateString(), 'operational');

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/income?from=2026-10-08&to=2026-10-08&compare=1')
            ->assertOk()
            ->json('data');

        $this->assertSame(800.0, (float) $data['previous']['gross']);
        $this->assertSame(100.0, (float) $data['previous']['expenses']);
        $this->assertSame(700.0, (float) $data['previous']['profit']);
    }

    public function test_dashboard_reports_profit(): void
    {
        $this->sale(1000, now(), null);
        $this->expense(400, now()->toDateString(), 'operational');

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertSame(400.0, (float) $data['expenses']['today']);
        $this->assertSame(1000.0, (float) $data['profit']['today']['income']);
        $this->assertSame(600.0, (float) $data['profit']['today']['net']);
        $this->assertSame(60.0, (float) $data['profit']['today']['margin']);
    }

    public function test_platform_subscription_is_recorded_as_an_expense(): void
    {
        $this->platformExpenseType();

        $payment = PlatformPayment::query()->create([
            'company_id' => $this->company->id,
            'type' => PlatformPayment::TYPE_SUBSCRIPTION_RENEWAL,
            'purpose' => PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL,
            'direction' => PlatformPayment::DIRECTION_COLLECTION,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => PlatformPayment::STATUS_PENDING,
            'initiated_at' => now(),
        ]);

        $service = app(PlatformPaymentService::class);
        $service->markPaid($payment);
        $service->markPaid($payment->fresh()); // idempotent

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', [
            'company_id' => $this->company->id,
            'source_type' => PlatformPayment::class,
            'source_id' => $payment->id,
            'category' => ExpenseType::CATEGORY_PLATFORM,
            'amount' => 10000,
        ]);
    }

    private function router(string $name): NetworkDevice
    {
        return app(RouterService::class)->create($this->company, [
            'gateway_type' => 'mikrotik',
            'name' => $name,
            'lan_ip' => '192.168.88.1',
            'api_username' => 'admin',
            'api_password' => 'secret',
        ], $this->owner);
    }

    private function sale(int $amount, Carbon $at, ?NetworkDevice $router = null, string $currency = 'TZS'): void
    {
        $plan = InternetPlan::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Plan '.Str::upper(Str::random(4)),
            'slug' => 'plan-'.Str::lower(Str::random(8)),
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => $amount,
            'status' => 'active',
        ]);

        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Customer '.Str::random(4),
            'phone' => '0755'.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $payment = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'initiated_at' => $at,
            'paid_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        RevenueRecord::query()->create([
            'company_id' => $this->company->id,
            'source' => 'mobile_money',
            'payment_transaction_id' => $payment->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'recognized',
            'recognized_at' => $at,
        ]);

        if ($router) {
            $grant = AccessGrant::query()->create([
                'company_id' => $this->company->id,
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'payment_transaction_id' => $payment->id,
                'source' => 'mobile_money',
                'starts_at' => $at,
                'expires_at' => $at->copy()->addDay(),
                'status' => 'active',
            ]);

            NetworkSession::query()->create([
                'company_id' => $this->company->id,
                'customer_id' => $customer->id,
                'access_grant_id' => $grant->id,
                'internet_plan_id' => $plan->id,
                'payment_transaction_id' => $payment->id,
                'network_device_id' => $router->id,
                'mac_address' => 'AA:BB:CC:00:00:'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT),
                'started_at' => $at,
                'status' => 'active',
                'upload_bytes' => 0,
                'download_bytes' => 0,
            ]);
        }
    }

    private function expense(int $amount, string $paidAt, string $category, ?NetworkDevice $router = null, string $currency = 'TZS'): Expense
    {
        $type = $this->expenseType($category);

        return Expense::query()->create([
            'company_id' => $this->company->id,
            'router_id' => $router?->id,
            'expense_type_id' => $type->id,
            'category' => $category,
            'source' => Expense::SOURCE_MANUAL,
            'description' => 'Test expense',
            'amount' => $amount,
            'currency' => $currency,
            'paid_at' => $paidAt,
            'paid_to' => 'Supplier',
            'status' => Expense::STATUS_RECORDED,
        ]);
    }

    private function expenseType(string $category): ExpenseType
    {
        return ExpenseType::query()->firstOrCreate(
            ['slug' => 'test-'.$category],
            [
                'name' => 'Test '.ucfirst($category),
                'category' => $category,
                'is_active' => true,
            ],
        );
    }

    private function platformExpenseType(): ExpenseType
    {
        return ExpenseType::query()->firstOrCreate(
            ['slug' => 'platform-cost'],
            [
                'name' => 'Platform Subscription',
                'category' => ExpenseType::CATEGORY_PLATFORM,
                'is_active' => true,
            ],
        );
    }
}
