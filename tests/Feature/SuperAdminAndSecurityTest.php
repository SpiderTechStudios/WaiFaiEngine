<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SuperAdminAndSecurityTest extends TestCase
{
    public function test_superadmin_can_list_and_suspend_users(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $user = $this->createUser();

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/users')
            ->assertOk();

        $this->withHeaders($this->authHeaders($admin))
            ->patchJson("/api/v1/superadmin/users/{$user->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
    }

    public function test_non_superadmin_cannot_access_platform_routes(): void
    {
        $user = $this->createUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/superadmin/users')
            ->assertForbidden();
    }

    public function test_superadmin_can_suspend_and_activate_company(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($admin))
            ->patchJson("/api/v1/superadmin/companies/{$company->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->withHeaders($this->authHeaders($admin))
            ->patchJson("/api/v1/superadmin/companies/{$company->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_suspended_user_cannot_use_existing_token(): void
    {
        $user = $this->createUser();
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_removed_member_cannot_access_company(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $staff = $this->createUser();
        $membership = $this->attach($staff, $company);
        $membership->forceFill(['status' => 'removed'])->save();

        $this->withHeaders($this->authHeaders($staff))
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    }

    public function test_company_isolation_on_packages(): void
    {
        $userA = $this->createUser();
        $this->createCompanyFor($userA);
        $userB = $this->createUser();
        $this->createCompanyFor($userB);

        $package = $this->withHeaders($this->authHeaders($userB))
            ->postJson('/api/v1/packages', [
                'name' => '1 Hour',
                'duration' => 1,
                'duration_unit' => 'HOURS',
                'price' => 1000,
            ])->assertCreated()
            ->json('data.id');

        $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/v1/packages/'.$package)
            ->assertNotFound();
    }
}
