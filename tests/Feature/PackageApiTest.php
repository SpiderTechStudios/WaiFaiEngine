<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\PaymentTransaction;
use App\Models\RevenueRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PackageApiTest extends TestCase
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
            'subdomain' => 'pkg-cafe',
            'payment_method' => 'both',
        ]);
        $this->headers = $this->authHeaders($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_package_list_includes_sales_metrics(): void
    {
        $plan = $this->plan();

        $this->sale($plan, 1500, now()->subDay());
        $this->sale($plan, 500, now()->subHours(2));

        $row = $this->withHeaders($this->headers)
            ->getJson('/api/v1/packages')
            ->assertOk()
            ->json('data.items.0');

        $this->assertSame(2, $row['sold']);
        $this->assertSame(2000.0, (float) $row['revenue']);
        $this->assertNotNull($row['last_sold_at']);
    }

    public function test_package_summary_reports_counts_best_seller_and_previous_revenue(): void
    {
        $best = $this->plan(['name' => 'Daily', 'price' => 1000]);
        $other = $this->plan(['name' => 'Weekly', 'price' => 2000]);
        $inactive = $this->plan(['name' => 'Old', 'price' => 3000]);

        $this->withHeaders($this->headers)
            ->patchJson('/api/v1/packages/'.$inactive->id, ['status' => 'inactive'])
            ->assertOk();

        $this->sale($best, 1000, now()->subDays(2));
        $this->sale($best, 1000, now()->subDays(1));
        $this->sale($other, 5000, now()->subDays(3));
        $this->sale($best, 1000, now()->subDays(35)); // previous 30-day window

        $data = $this->withHeaders($this->headers)
            ->getJson('/api/v1/packages/summary?period=30d')
            ->assertOk()
            ->json('data');

        $this->assertSame(3, $data['total']);
        $this->assertSame(2, $data['active']);
        $this->assertSame(1, $data['inactive']);
        $this->assertSame('Daily', $data['best_seller']['name']);
        $this->assertSame(2, $data['best_seller']['sold']);
        $this->assertSame(7000.0, (float) $data['revenue']);
        $this->assertSame(1000.0, (float) $data['previous_revenue']);
        $this->assertSame(1500, $data['average_price']);
        $this->assertSame(1000, $data['lowest_price']);
        $this->assertSame(2000, $data['highest_price']);
    }

    public function test_patch_accepts_status_and_portal_visibility(): void
    {
        $plan = $this->plan();

        $this->withHeaders($this->headers)
            ->patchJson('/api/v1/packages/'.$plan->id, ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->withHeaders($this->headers)
            ->patchJson('/api/v1/packages/'.$plan->id, [
                'status' => 'active',
                'visible_on_portal' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.visible_on_portal', false);
    }

    public function test_delete_is_blocked_with_409_when_package_has_history(): void
    {
        $plan = $this->plan();

        PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 1000,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->deleteJson('/api/v1/packages/'.$plan->id)
            ->assertStatus(409);

        $this->assertSame(1, $response->json('data.payments'));
        $this->assertSame(0, $response->json('data.vouchers'));
        $this->assertStringContainsString('Deactivate it instead', $response->json('message'));
        $this->assertDatabaseHas('internet_plans', ['id' => $plan->id, 'deleted_at' => null]);

        // The operator can deactivate it instead.
        $this->withHeaders($this->headers)
            ->patchJson('/api/v1/packages/'.$plan->id, ['status' => 'inactive'])
            ->assertOk();
    }

    public function test_delete_removes_unused_package(): void
    {
        $plan = $this->plan();

        $this->withHeaders($this->headers)
            ->deleteJson('/api/v1/packages/'.$plan->id)
            ->assertOk();

        $this->assertSoftDeleted('internet_plans', ['id' => $plan->id]);
    }

    public function test_duplicate_creates_inactive_hidden_copy(): void
    {
        $plan = $this->plan([
            'name' => 'Daily',
            'price' => 1500,
            'badge' => 'Popular',
            'speed_download_mbps' => 10,
            'data_cap_mb' => 2048,
            'devices_allowed' => 2,
        ]);

        $copy = $this->withHeaders($this->headers)
            ->postJson('/api/v1/packages/'.$plan->id.'/duplicate')
            ->assertCreated()
            ->json('data');

        $this->assertNotSame($plan->id, $copy['id']);
        $this->assertSame('Daily (copy)', $copy['name']);
        $this->assertSame('inactive', $copy['status']);
        $this->assertFalse($copy['visible_on_portal']);
        $this->assertSame(1500.0, (float) $copy['price']);
        $this->assertSame(10, $copy['speed_download_mbps']);
        $this->assertSame(2048, $copy['data_cap_mb']);
    }

    public function test_reorder_sets_portal_order(): void
    {
        $a = $this->plan(['name' => 'A']);
        $b = $this->plan(['name' => 'B']);
        $c = $this->plan(['name' => 'C']);

        $this->withHeaders($this->headers)
            ->putJson('/api/v1/packages/order', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $ids = collect($this->withHeaders($this->headers)
            ->getJson('/api/v1/packages')
            ->assertOk()
            ->json('data.items'))
            ->pluck('id')
            ->all();

        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_limits_and_visibility_control_the_portal(): void
    {
        $this->plan(['name' => 'Visible', 'sort_order' => 2]);
        $hidden = $this->plan(['name' => 'Hidden', 'visible_on_portal' => false]);
        $this->plan(['name' => 'First', 'sort_order' => 1]);

        $packages = $this->getJson('/api/v1/portal/pkg-cafe')
            ->assertOk()
            ->json('data.packages');

        $names = collect($packages)->pluck('name')->all();

        $this->assertSame(['First', 'Visible'], $names);
        $this->assertNotContains($hidden->name, $names);
    }

    public function test_list_filters_by_status_and_search(): void
    {
        $daily = $this->plan(['name' => 'Daily Data']);
        $old = $this->plan(['name' => 'Old Plan']);

        $this->withHeaders($this->headers)
            ->patchJson('/api/v1/packages/'.$old->id, ['status' => 'inactive'])
            ->assertOk();

        $statusRows = $this->withHeaders($this->headers)
            ->getJson('/api/v1/packages?status=inactive')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $statusRows);
        $this->assertSame('Old Plan', $statusRows[0]['name']);

        $searchRows = $this->withHeaders($this->headers)
            ->getJson('/api/v1/packages?search=Daily')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $searchRows);
        $this->assertSame($daily->id, $searchRows[0]['id']);
    }

    public function test_other_company_cannot_touch_package(): void
    {
        $plan = $this->plan();

        $other = $this->createUser();
        $this->createCompanyFor($other);
        $otherHeaders = $this->authHeaders($other);

        $this->withHeaders($otherHeaders)->getJson('/api/v1/packages/'.$plan->id)->assertNotFound();
        $this->withHeaders($otherHeaders)->postJson('/api/v1/packages/'.$plan->id.'/duplicate')->assertNotFound();
        $this->withHeaders($otherHeaders)->deleteJson('/api/v1/packages/'.$plan->id)->assertNotFound();
        $this->withHeaders($otherHeaders)
            ->putJson('/api/v1/packages/order', ['ids' => [$plan->id]])
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function plan(array $overrides = []): InternetPlan
    {
        $payload = array_merge([
            'name' => 'Plan '.Str::upper(Str::random(4)),
            'price' => 1000,
            'duration' => 1,
            'duration_unit' => 'DAYS',
        ], $overrides);

        $id = $this->withHeaders($this->headers)
            ->postJson('/api/v1/packages', $payload)
            ->assertCreated()
            ->json('data.id');

        return InternetPlan::query()->findOrFail($id);
    }

    private function sale(InternetPlan $plan, int $amount, Carbon $recognizedAt): void
    {
        $payment = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => $amount,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'initiated_at' => $recognizedAt,
            'paid_at' => $recognizedAt,
            'created_at' => $recognizedAt,
            'updated_at' => $recognizedAt,
        ]);

        RevenueRecord::query()->create([
            'company_id' => $this->company->id,
            'source' => 'mobile_money',
            'payment_transaction_id' => $payment->id,
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'recognized',
            'recognized_at' => $recognizedAt,
        ]);
    }
}
