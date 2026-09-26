<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\ExpenseTypeSeeder;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ExpenseTypeSeeder::class);
    }

    private function typeId(string $slug): int
    {
        return (int) ExpenseType::query()->where('slug', $slug)->value('id');
    }

    private function createRouter(User $owner): int
    {
        return (int) $this->withHeaders($this->authHeaders($owner))->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Expense Router '.fake()->unique()->numerify('###'),
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data.id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'expense_type_id' => $this->typeId('internet'),
            'description' => 'Monthly internet bundle',
            'amount' => 50000,
            'currency' => 'TZS',
            'paid_at' => '2026-09-25',
            'paid_to' => 'Airtel',
            'reference' => 'AIR-09252026',
            'notes' => 'Monthly data package',
        ], $overrides);
    }

    public function test_owner_can_create_list_and_show_expense(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $routerId = $this->createRouter($owner);

        $id = $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'router_id' => $routerId,
            'description' => 'Monthly internet bundle for Router #12',
        ]))->assertCreated()
            ->assertJsonPath('data.description', 'Monthly internet bundle for Router #12')
            ->assertJsonPath('data.amount', '50000.00')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.paid_at', '2026-09-25')
            ->assertJsonPath('data.paid_to', 'Airtel')
            ->assertJsonPath('data.category', 'operational')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.router.id', $routerId)
            ->json('data.id');

        $this->assertDatabaseHas('expenses', [
            'id' => $id,
            'router_id' => $routerId,
            'category' => 'operational',
            'source' => 'manual',
        ]);

        $this->withHeaders($headers)->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $id);

        $this->withHeaders($headers)->getJson('/api/v1/expenses/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.expense_type.slug', 'internet');
    }

    public function test_business_wide_expense_has_null_router_and_is_filterable(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $routerId = $this->createRouter($owner);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('electricity'),
            'description' => 'Office electricity bill',
            'paid_to' => 'TANESCO',
        ]))->assertCreated()->assertJsonPath('data.router', null);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload(['router_id' => $routerId]))->assertCreated();

        $this->withHeaders($headers)->getJson('/api/v1/expenses?scope=business')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.router', null);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?router_id=null')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?scope=router')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.router.id', $routerId);
    }

    public function test_category_is_derived_from_expense_type_and_client_supplied_value_is_rejected(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('platform-subscription'),
            'description' => 'WaiFai monthly subscription',
            'paid_to' => 'WaiFai',
        ]))->assertCreated()->assertJsonPath('data.category', 'platform');

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'category' => 'platform',
        ]))->assertStatus(422)->assertJsonPath('data.category.0', 'The category field is prohibited.');
    }

    public function test_list_supports_router_type_category_date_paid_to_currency_and_search_filters(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $routerId = $this->createRouter($owner);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'router_id' => $routerId,
            'paid_at' => '2026-09-05',
            'paid_to' => 'Airtel',
            'description' => 'Router internet',
        ]))->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('electricity'),
            'paid_at' => '2026-09-20',
            'paid_to' => 'TANESCO',
            'description' => 'Site electricity',
        ]))->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('platform-subscription'),
            'paid_at' => '2026-08-01',
            'paid_to' => 'WaiFai',
            'description' => 'Platform subscription',
        ]))->assertCreated();

        $this->withHeaders($headers)->getJson('/api/v1/expenses?router_id='.$routerId)
            ->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?expense_type_id='.$this->typeId('electricity'))
            ->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?category=platform')
            ->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?category=operational')
            ->assertOk()->assertJsonPath('data.meta.total', 2);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?from=2026-09-01&to=2026-09-30')
            ->assertOk()->assertJsonPath('data.meta.total', 2);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?paid_to=TANESCO')
            ->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?search=electricity')
            ->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($headers)->getJson('/api/v1/expenses?currency=TZS&sort=amount&direction=asc')
            ->assertOk()->assertJsonPath('data.meta.total', 3);
    }

    public function test_summary_totals_platform_and_operational(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('platform-subscription'),
            'amount' => 50000,
            'paid_to' => 'WaiFai',
            'description' => 'Platform subscription',
        ]))->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('electricity'),
            'amount' => 20000,
            'paid_to' => 'TANESCO',
            'description' => 'Electricity',
        ]))->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $this->typeId('internet'),
            'amount' => 30000,
            'paid_to' => 'Airtel',
            'description' => 'Internet',
        ]))->assertCreated();

        $this->withHeaders($headers)->getJson('/api/v1/expenses/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 100000)
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.platform_total', 50000)
            ->assertJsonPath('data.operational_total', 50000)
            ->assertJsonPath('data.count', 3);

        $this->withHeaders($headers)->getJson('/api/v1/expenses/summary?category=operational')
            ->assertOk()
            ->assertJsonPath('data.operational_total', 50000)
            ->assertJsonPath('data.platform_total', 0);
    }

    public function test_router_expense_report_returns_summary_and_breakdown(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $routerId = $this->createRouter($owner);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'router_id' => $routerId,
            'expense_type_id' => $this->typeId('internet'),
            'amount' => 100000,
        ]))->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'router_id' => $routerId,
            'expense_type_id' => $this->typeId('electricity'),
            'amount' => 50000,
            'paid_to' => 'TANESCO',
        ]))->assertCreated();

        $this->withHeaders($headers)->getJson('/api/v1/routers/'.$routerId.'/expenses')
            ->assertOk()
            ->assertJsonPath('data.router.id', $routerId)
            ->assertJsonPath('data.summary.total', 150000)
            ->assertJsonPath('data.summary.currency', 'TZS')
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.breakdown.0.expense_type', 'Internet')
            ->assertJsonPath('data.breakdown.0.total', 100000);
    }

    public function test_update_recomputes_category_and_router_and_supports_clearing_router(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $routerId = $this->createRouter($owner);

        $id = $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'router_id' => $routerId,
        ]))->assertCreated()->json('data.id');

        $this->withHeaders($headers)->patchJson('/api/v1/expenses/'.$id, [
            'expense_type_id' => $this->typeId('platform-subscription'),
            'amount' => 75000,
            'router_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.category', 'platform')
            ->assertJsonPath('data.amount', '75000.00')
            ->assertJsonPath('data.router', null);

        $this->assertDatabaseHas('expenses', [
            'id' => $id,
            'router_id' => null,
            'category' => 'platform',
        ]);
    }

    public function test_delete_soft_deletes_the_expense(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $id = $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload())->assertCreated()->json('data.id');

        $this->withHeaders($headers)->deleteJson('/api/v1/expenses/'.$id)->assertOk();

        $this->assertSoftDeleted('expenses', ['id' => $id]);
        $this->withHeaders($headers)->getJson('/api/v1/expenses/'.$id)->assertNotFound();
    }

    public function test_inactive_expense_type_is_rejected(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $type = ExpenseType::query()->where('slug', 'internet')->firstOrFail();
        $type->forceFill(['is_active' => false])->save();

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload([
            'expense_type_id' => $type->id,
        ]))->assertStatus(422)->assertJsonPath('data.expense_type_id.0', 'The selected expense type is inactive.');
    }

    public function test_invalid_amount_and_currency_are_rejected(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload(['amount' => 0]))
            ->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload(['amount' => -100]))
            ->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->payload(['currency' => 'US']))
            ->assertStatus(422);
    }

    public function test_cannot_attach_expense_to_another_companys_router(): void
    {
        $ownerA = $this->createUser();
        $this->createCompanyFor($ownerA);
        $routerId = $this->createRouter($ownerA);

        $ownerB = $this->createUser();
        $this->createCompanyFor($ownerB);
        $headersB = $this->authHeaders($ownerB);

        $this->withHeaders($headersB)->postJson('/api/v1/expenses', $this->payload([
            'router_id' => $routerId,
        ]))->assertStatus(422)
            ->assertJsonPath('data.router_id.0', 'The selected router does not belong to your account.');
    }

    public function test_cannot_view_or_list_another_companys_expenses(): void
    {
        $ownerA = $this->createUser();
        $this->createCompanyFor($ownerA);
        $id = $this->withHeaders($this->authHeaders($ownerA))
            ->postJson('/api/v1/expenses', $this->payload())->assertCreated()->json('data.id');

        $ownerB = $this->createUser();
        $this->createCompanyFor($ownerB);
        $headersB = $this->authHeaders($ownerB);

        $this->withHeaders($headersB)->getJson('/api/v1/expenses')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->withHeaders($headersB)->getJson('/api/v1/expenses/'.$id)->assertNotFound();
        $this->withHeaders($headersB)->patchJson('/api/v1/expenses/'.$id, ['amount' => 1])->assertNotFound();
        $this->withHeaders($headersB)->deleteJson('/api/v1/expenses/'.$id)->assertNotFound();
    }

    public function test_system_generated_expense_cannot_be_modified_or_deleted(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $type = ExpenseType::query()->where('slug', 'platform-subscription')->firstOrFail();

        $expense = app(ExpenseService::class)->recordSystemExpense(
            company: $company,
            type: $type,
            data: [
                'description' => 'WaiFai monthly subscription',
                'amount' => 10000,
                'currency' => 'TZS',
                'paid_at' => '2026-09-01',
                'paid_to' => 'WaiFai',
            ],
        );

        $this->assertSame('system', $expense->source);

        $this->withHeaders($headers)->patchJson('/api/v1/expenses/'.$expense->id, ['amount' => 1])
            ->assertForbidden();

        $this->withHeaders($headers)->deleteJson('/api/v1/expenses/'.$expense->id)
            ->assertForbidden();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'deleted_at' => null]);
    }

    public function test_company_user_can_list_active_expense_types_only(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        ExpenseType::query()->where('slug', 'repair')->update(['is_active' => false]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/expense-types')->assertOk();

        $slugs = array_column($response->json('data'), 'slug');

        $this->assertContains('internet', $slugs);
        $this->assertNotContains('repair', $slugs);
    }

    public function test_operator_without_update_permission_cannot_delete(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $operator = $this->createUser();
        $this->attach($operator, $company, 'operator');
        $operator->forceFill(['current_company_id' => $company->id])->save();

        $id = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/expenses', $this->payload())->assertCreated()->json('data.id');

        $this->withHeaders($this->authHeaders($operator))
            ->deleteJson('/api/v1/expenses/'.$id)
            ->assertForbidden();
    }

    public function test_expenses_table_is_scoped_by_company_id(): void
    {
        $ownerA = $this->createUser();
        $companyA = $this->createCompanyFor($ownerA);
        $this->withHeaders($this->authHeaders($ownerA))->postJson('/api/v1/expenses', $this->payload())->assertCreated();

        $ownerB = $this->createUser();
        $companyB = $this->createCompanyFor($ownerB);
        $this->withHeaders($this->authHeaders($ownerB))->postJson('/api/v1/expenses', $this->payload())->assertCreated();

        $this->assertSame(1, Expense::query()->where('company_id', $companyA->id)->count());
        $this->assertSame(1, Expense::query()->where('company_id', $companyB->id)->count());
        $this->assertSame(2, Expense::query()->count());
    }
}
