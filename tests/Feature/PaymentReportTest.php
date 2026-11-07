<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReportTest extends TestCase
{
    private User $owner;

    private Company $company;

    /** @var array<string, string> */
    private array $headers;

    private InternetPlan $sixHours;

    private InternetPlan $oneDay;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'UTC'));

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', [
            'subdomain' => 'payments-report',
        ]);
        $this->headers = $this->authHeaders($this->owner);

        $this->sixHours = $this->plan('masaa 6', 500);
        $this->oneDay = $this->plan('masaa 24', 1000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payments_can_be_filtered_by_status_provider_package_phone_and_date(): void
    {
        $alice = $this->customer('Alice Mushi', '0712345678');
        $bob = $this->customer('Bob Mwita', '0755555555');

        $paid = $this->payment([
            'customer_id' => $alice->id,
            'internet_plan_id' => $this->sixHours->id,
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'amount' => 500,
            'created_at' => '2026-10-01 10:00:00',
        ]);

        $this->payment([
            'customer_id' => $bob->id,
            'internet_plan_id' => $this->oneDay->id,
            'payment_method' => 'MixxByYas',
            'status' => 'pending',
            'amount' => 1000,
            'created_at' => '2026-10-02 10:00:00',
        ]);

        $this->payment([
            'payment_method' => 'AirtelMoney',
            'status' => 'failed',
            'amount' => 200,
            'created_at' => '2026-10-03 10:00:00',
        ]);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?status=paid')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference', $paid->reference);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?provider=mpesa')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference', $paid->reference);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?package_id='.$this->oneDay->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.payment_method', 'MixxByYas');

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?q=0712345678')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference', $paid->reference);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?q=Alice')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference', $paid->reference);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?from=2026-10-02&to=2026-10-03')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_payments_can_be_sorted_by_amount(): void
    {
        $this->payment(['status' => 'paid', 'payment_method' => 'Mpesa', 'amount' => 500, 'created_at' => '2026-10-01 10:00:00']);
        $this->payment(['status' => 'paid', 'payment_method' => 'Mpesa', 'amount' => 100, 'created_at' => '2026-10-02 10:00:00']);
        $this->payment(['status' => 'paid', 'payment_method' => 'Mpesa', 'amount' => 300, 'created_at' => '2026-10-03 10:00:00']);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments?sort=amount')
            ->assertOk();

        $this->assertSame([100.0, 300.0, 500.0], array_map(
            fn (array $item): float => (float) $item['amount'],
            $response->json('data.items'),
        ));
    }

    public function test_payment_summary_follows_the_filters(): void
    {
        $alice = $this->customer('Alice Mushi', '0712345678');

        $this->payment([
            'customer_id' => $alice->id,
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'amount' => 500,
            'created_at' => '2026-10-01 10:00:00',
        ]);
        $this->payment([
            'payment_method' => 'MixxByYas',
            'status' => 'pending',
            'amount' => 1000,
            'created_at' => '2026-10-02 10:00:00',
        ]);
        $this->payment([
            'payment_method' => 'AirtelMoney',
            'status' => 'failed',
            'amount' => 200,
            'created_at' => '2026-10-03 10:00:00',
        ]);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.paid.count', 1)
            ->assertJsonPath('data.paid.amount', 500)
            ->assertJsonPath('data.pending.count', 1)
            ->assertJsonPath('data.pending.stale_count', 1)
            ->assertJsonPath('data.failed.count', 1)
            ->assertJsonPath('data.by_status.paid', 1)
            ->assertJsonPath('data.by_status.pending', 1)
            ->assertJsonPath('data.by_status.failed', 1);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/payments/summary?status=paid')
            ->assertOk()
            ->assertJsonPath('data.paid.count', 1)
            ->assertJsonPath('data.pending.count', 0)
            ->assertJsonPath('data.failed.count', 0);
    }

    public function test_payments_can_be_exported_as_csv_with_the_same_filters(): void
    {
        $alice = $this->customer('Alice Mushi', '0712345678');

        $paid = $this->payment([
            'customer_id' => $alice->id,
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'amount' => 500,
            'created_at' => '2026-10-01 10:00:00',
        ]);
        $pending = $this->payment([
            'payment_method' => 'MixxByYas',
            'status' => 'pending',
            'amount' => 1000,
            'created_at' => '2026-10-02 10:00:00',
        ]);

        $response = $this->withHeaders($this->headers)
            ->get('/api/v1/payments/export?status=paid')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('reference,created_at', $csv);
        $this->assertStringContainsString($paid->reference, $csv);
        $this->assertStringContainsString('Alice Mushi', $csv);
        $this->assertStringNotContainsString($pending->reference, $csv);
    }

    private function customer(string $name, string $phone): Customer
    {
        return Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payment(array $overrides = []): PaymentTransaction
    {
        $createdAt = Carbon::parse($overrides['created_at'] ?? '2026-10-01 10:00:00', 'UTC');

        $payment = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $overrides['customer_id'] ?? null,
            'internet_plan_id' => $overrides['internet_plan_id'] ?? $this->sixHours->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $overrides['amount'] ?? 500,
            'currency' => 'TZS',
            'payment_method' => $overrides['payment_method'] ?? 'Mpesa',
            'status' => $overrides['status'] ?? 'pending',
            'initiated_at' => $createdAt,
        ]);

        $payment->forceFill(['created_at' => $createdAt])->save();

        return $payment;
    }

    private function plan(string $name, float $price): InternetPlan
    {
        return InternetPlan::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'duration' => 6,
            'duration_unit' => 'HOURS',
            'price' => $price,
            'status' => InternetPlan::STATUS_ACTIVE,
        ]);
    }
}
