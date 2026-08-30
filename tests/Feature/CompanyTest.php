<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompanyTest extends TestCase
{
    public function test_registration_creates_company_and_owner(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $result = $this->completePaidSignup([
            'business_name' => 'ABC Internet',
            'email' => 'jane@example.com',
            'portal_subdomain' => 'abc',
        ]);

        $result['response']
            ->assertJsonPath('data.current_company.name', 'ABC Internet')
            ->assertJsonPath('data.current_company.subdomain', 'abc')
            ->assertJsonPath('data.membership.role.slug', 'owner');

        $this->assertDatabaseHas('user_companies', [
            'user_id' => $result['response']->json('data.user.id'),
            'role_id' => $this->roleId('owner'),
            'status' => 'active',
        ]);
    }

    public function test_company_crud_routes_are_removed(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/companies', ['name' => 'Nope'])
            ->assertNotFound();
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
