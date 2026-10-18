<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBillingTest extends TestCase
{
    private User $admin;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-08 09:00:00', 'UTC'));
        config(['platform.subscription_monthly' => 20000]);

        $this->admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $this->headers = $this->authHeaders($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summary_reports_platform_revenue_and_subscription_health(): void
    {
        $active = $this->company('Active Co', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->addDays(3),
        ]);
        $pastDue = $this->company('Past Due Co', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->subDays(2),
        ]);
        $expired = $this->company('Expired Co', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->subDays(30),
        ]);
        $this->company('Suspended Co', [
            'subscription_status' => 'suspended',
            'subscription_period_ends_at' => now()->addDays(10),
        ]);

        $this->platformPayment($active, PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION, 20000, 'paid');
        $this->platformPayment($pastDue, PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL, 20000, 'paid');
        $this->platformPayment($active, PlatformPayment::PURPOSE_INSTALLATION_REQUEST, 50000, 'paid');
        $this->platformPayment($expired, PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL, 20000, 'pending');

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/summary')
            ->assertOk()
            ->json('data');

        $this->assertSame(20000.0, (float) $data['monthly_fee']);
        $this->assertSame(20000.0, (float) $data['mrr']);
        $this->assertSame(4, $data['clients']['total']);
        $this->assertSame(1, $data['clients']['active']);
        $this->assertSame(1, $data['clients']['past_due']);
        $this->assertSame(1, $data['clients']['expired']);
        $this->assertSame(1, $data['clients']['suspended']);

        $this->assertSame(90000.0, (float) $data['revenue']['total']);
        $this->assertSame(3, $data['revenue']['count']);
        $this->assertSame(20000.0, (float) $data['revenue']['by_purpose']['platform_subscription']['total']);
        $this->assertSame(50000.0, (float) $data['revenue']['by_purpose']['installation_request']['total']);

        $this->assertSame(1, $data['due_soon']['count']);
        $this->assertSame(1, $data['overdue']['count']);
        $this->assertSame(20000.0, (float) $data['overdue']['amount']);
    }

    public function test_clients_list_is_enriched_and_filterable(): void
    {
        $alpha = $this->company('Alpha Net', [
            'subdomain' => 'alpha',
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->addDays(4),
        ]);
        $this->company('Beta Net', [
            'subdomain' => 'beta',
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->subDays(30),
        ]);
        $this->platformPayment($alpha, PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION, 20000, 'paid');

        $page = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/clients')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $page['meta']['total']);
        $row = collect($page['items'])->firstWhere('id', $alpha->id);
        $this->assertSame('active', $row['subscription_status']);
        $this->assertSame(4, $row['days_remaining']);
        $this->assertSame(20000.0, (float) $row['total_paid']);
        $this->assertNotNull($row['last_payment_at']);

        $expiredOnly = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/clients?subscription_status=expired')
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $expiredOnly['meta']['total']);

        $search = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/clients?search=alpha')
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $search['meta']['total']);
        $this->assertSame('Alpha Net', $search['items'][0]['name']);
    }

    public function test_client_detail_includes_subscription_usage_and_payments(): void
    {
        $company = $this->company('Gamma Net', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->addDays(20),
        ]);
        $this->platformPayment($company, PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION, 20000, 'paid');

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/clients/'.$company->id)
            ->assertOk()
            ->json('data');

        $this->assertSame('monthly', $data['subscription']['plan']);
        $this->assertSame(20000.0, (float) $data['subscription']['amount']);
        $this->assertTrue($data['subscription']['is_access_allowed']);
        $this->assertArrayHasKey('routers_total', $data['usage']);
        $this->assertArrayHasKey('active_sessions', $data['usage']);
        $this->assertNotEmpty($data['payments']);
        $this->assertSame('platform_subscription', $data['payments'][0]['purpose']);
    }

    public function test_renewals_lists_companies_due_and_overdue(): void
    {
        $due = $this->company('Due Co', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->addDays(2),
        ]);
        $overdue = $this->company('Overdue Co', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->subDays(30),
        ]);

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/renewals?days=7')
            ->assertOk()
            ->json('data');

        $ids = array_column($data['items'], 'id');
        $this->assertContains($due->id, $ids);
        $this->assertContains($overdue->id, $ids);
        // Sorted by urgency: most overdue first.
        $this->assertSame($overdue->id, $data['items'][0]['id']);
    }

    public function test_platform_payments_list_filters_by_purpose_and_status(): void
    {
        $company = $this->company('Delta Net');
        $this->platformPayment($company, PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION, 20000, 'paid');
        $this->platformPayment($company, PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION, 20000, 'paid');
        $this->platformPayment($company, PlatformPayment::PURPOSE_SUBSCRIPTION_RENEWAL, 20000, 'pending');

        $paid = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/billing/payments?purpose=platform_subscription&status=paid')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $paid['meta']['total']);
        $this->assertSame('Delta Net', $paid['items'][0]['company_name']);
    }

    public function test_admin_dashboard_includes_platform_block(): void
    {
        $company = $this->company('Epsilon Net', [
            'subscription_status' => 'active',
            'subscription_period_ends_at' => now()->addDays(5),
        ]);
        $this->platformPayment($company, PlatformPayment::PURPOSE_PLATFORM_SUBSCRIPTION, 20000, 'paid');

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertSame(20000.0, (float) $data['platform']['mrr']);
        $this->assertSame(20000.0, (float) $data['platform']['revenue_this_month']);
        $this->assertSame(1, $data['platform']['clients']['active']);
    }

    public function test_company_user_cannot_access_platform_billing(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->getJson('/api/v1/admin/billing/summary')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function company(string $name, array $overrides = []): Company
    {
        $owner = $this->createUser();

        return $this->createCompanyFor($owner, 'owner', array_merge(['name' => $name], $overrides));
    }

    private function platformPayment(Company $company, string $purpose, float $amount, string $status): PlatformPayment
    {
        return PlatformPayment::query()->create([
            'company_id' => $company->id,
            'type' => $purpose,
            'purpose' => $purpose,
            'direction' => PlatformPayment::DIRECTION_COLLECTION,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => $status,
            'initiated_at' => now(),
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }
}
