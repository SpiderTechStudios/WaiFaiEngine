<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    public function test_user_can_create_company_and_becomes_owner(): void
    {
        $user = $this->createUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/companies', [
                'name' => 'ABC Internet',
                'email' => 'abc@example.com',
                'phone' => '0700000000',
            ]);

        $response->assertCreated()->assertJsonPath('data.name', 'ABC Internet');

        $this->assertDatabaseHas('user_companies', [
            'user_id' => $user->id,
            'role_id' => $this->roleId('owner'),
            'status' => 'active',
        ]);
    }

    public function test_list_returns_only_membership_companies(): void
    {
        $user = $this->createUser();
        $mine = $this->createCompanyFor($user);
        $otherOwner = $this->createUser();
        $this->createCompanyFor($otherOwner);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/companies')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertCount(1, $ids);
    }

    public function test_user_cannot_view_another_company(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);
        $other = $this->createCompanyFor($this->createUser());

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/companies/'.$other->id)
            ->assertForbidden();
    }

    public function test_user_can_switch_between_companies_and_roles_do_not_leak(): void
    {
        $user = $this->createUser();
        $companyX = $this->createCompanyFor($user, 'manager');
        $companyY = $this->createCompanyFor($user, 'operator');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertCount(2, $login->json('data.companies'));

        $token = $login->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/company/switch', [
            'company_id' => $companyX->id,
        ])->assertOk()
            ->assertJsonPath('data.current_company.id', $companyX->id)
            ->assertJsonPath('data.membership.role.slug', 'manager');

        $this->withToken($token)->postJson('/api/v1/auth/company/switch', [
            'company_id' => $companyY->id,
        ])->assertOk()
            ->assertJsonPath('data.current_company.id', $companyY->id)
            ->assertJsonPath('data.membership.role.slug', 'operator');

        $permissions = $this->withToken($token)->getJson('/api/v1/auth/me')->json('data.permissions');
        $this->assertNotContains('staff.create', $permissions);
    }

    public function test_user_cannot_switch_to_unauthorized_company(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);
        $other = $this->createCompanyFor($this->createUser());

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/auth/company/switch', ['company_id' => $other->id])
            ->assertForbidden();
    }
}
