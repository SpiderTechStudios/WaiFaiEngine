<?php

namespace Tests\Feature;

use App\Models\ExpenseType;
use Database\Seeders\ExpenseTypeSeeder;
use Tests\TestCase;

class AdminExpenseTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ExpenseTypeSeeder::class);
    }

    public function test_admin_can_create_list_update_and_delete_an_unused_type(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $headers = $this->authHeaders($admin);

        $id = $this->withHeaders($headers)->postJson('/api/v1/admin/expense-types', [
            'name' => 'Security Guard',
            'description' => 'On-site security',
            'category' => 'operational',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Security Guard')
            ->assertJsonPath('data.slug', 'security-guard')
            ->assertJsonPath('data.category', 'operational')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->withHeaders($headers)->getJson('/api/v1/admin/expense-types')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 17);

        $this->withHeaders($headers)->getJson('/api/v1/admin/expense-types/'.$id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Security Guard');

        $this->withHeaders($headers)->patchJson('/api/v1/admin/expense-types/'.$id, [
            'name' => 'Security Services',
            'category' => 'platform',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Security Services')
            ->assertJsonPath('data.slug', 'security-services')
            ->assertJsonPath('data.category', 'platform');

        $this->withHeaders($headers)->deleteJson('/api/v1/admin/expense-types/'.$id)->assertOk();
        $this->assertDatabaseMissing('expense_types', ['id' => $id]);
    }

    public function test_admin_can_deactivate_and_reactivate_a_type(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $headers = $this->authHeaders($admin);

        $type = ExpenseType::query()->where('slug', 'internet')->firstOrFail();

        $this->withHeaders($headers)->postJson('/api/v1/admin/expense-types/'.$type->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($type->fresh()->is_active);

        $this->withHeaders($headers)->postJson('/api/v1/admin/expense-types/'.$type->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_cannot_delete_a_type_that_is_in_use(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);
        $headers = $this->authHeaders($admin);

        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $type = ExpenseType::query()->where('slug', 'internet')->firstOrFail();

        $this->withHeaders($this->authHeaders($owner))->postJson('/api/v1/expenses', [
            'expense_type_id' => $type->id,
            'description' => 'Internet bundle',
            'amount' => 10000,
            'currency' => 'TZS',
            'paid_at' => '2026-09-01',
            'paid_to' => 'Airtel',
        ])->assertCreated();

        $this->withHeaders($headers)->deleteJson('/api/v1/admin/expense-types/'.$type->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('expense_types', ['id' => $type->id]);
    }

    public function test_company_users_cannot_manage_expense_types(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->getJson('/api/v1/admin/expense-types')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/v1/admin/expense-types', [
            'name' => 'Sneaky',
            'category' => 'operational',
        ])->assertForbidden();
    }

    public function test_invalid_category_is_rejected(): void
    {
        $admin = $this->createUser(['is_admin' => true, 'status' => 'active']);

        $this->withHeaders($this->authHeaders($admin))->postJson('/api/v1/admin/expense-types', [
            'name' => 'Bad',
            'category' => 'capital',
        ])->assertStatus(422);
    }
}
