<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StaffInvitedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    public function test_owner_can_add_existing_user(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $staff = $this->createUser(['email' => 'staff@example.com']);

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/staff', [
                'email' => 'staff@example.com',
                'role' => 'operator',
            ])->assertCreated()
            ->assertJsonPath('data.user.id', $staff->id)
            ->assertJsonPath('data.role.slug', 'operator');
    }

    public function test_owner_can_invite_new_user(): void
    {
        Notification::fake();
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/staff', [
                'name' => 'New Staff',
                'email' => 'new@example.com',
                'role' => 'cashier',
            ])->assertCreated();

        $invited = User::query()->where('email', 'new@example.com')->first();
        $this->assertNotNull($invited);
        Notification::assertSentTo($invited, StaffInvitedNotification::class);
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $staff = $this->createUser();
        $this->attach($staff, $company, 'operator');

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/staff', [
                'email' => $staff->email,
                'role' => 'operator',
            ])->assertStatus(422);
    }

    public function test_role_can_be_updated(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $staff = $this->createUser();
        $membership = $this->attach($staff, $company, 'operator');

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson("/api/v1/staff/{$membership->id}/role", [
                'role' => 'manager',
            ])->assertOk()
            ->assertJsonPath('data.role.slug', 'manager');
    }

    public function test_membership_can_be_suspended_and_activated(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $staff = $this->createUser();
        $membership = $this->attach($staff, $company, 'operator');

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson("/api/v1/staff/{$membership->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->withHeaders($this->authHeaders($staff->fresh()))
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson("/api/v1/staff/{$membership->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_membership_can_be_removed_without_deleting_user(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $staff = $this->createUser();
        $membership = $this->attach($staff, $company, 'operator');

        $this->withHeaders($this->authHeaders($owner))
            ->deleteJson("/api/v1/staff/{$membership->id}")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('user_companies', [
            'id' => $membership->id,
            'status' => 'removed',
        ]);
    }

    public function test_owner_cannot_be_removed_or_have_role_changed(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $ownerMembership = $owner->membershipFor($company);

        $this->withHeaders($this->authHeaders($owner))
            ->deleteJson("/api/v1/staff/{$ownerMembership->id}")
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson("/api/v1/staff/{$ownerMembership->id}/role", [
                'role' => 'manager',
            ])->assertForbidden();
    }

    public function test_manager_cannot_assign_owner(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $manager = $this->createUser();
        $this->attach($manager, $company, 'manager');
        $staff = $this->createUser();
        $membership = $this->attach($staff, $company, 'operator');

        $this->withHeaders($this->authHeaders($manager))
            ->patchJson("/api/v1/staff/{$membership->id}", [
                'role' => 'owner',
            ])->assertStatus(422);
    }

    public function test_ownership_can_be_transferred(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $manager = $this->createUser();
        $membership = $this->attach($manager, $company, 'manager');

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/ownership/transfer', [
                'membership_id' => $membership->id,
            ])->assertOk();

        $this->assertSame('owner', $membership->fresh()->role->slug);
        $this->assertSame('manager', $owner->membershipFor($company)->role->slug);
    }

    public function test_operator_cannot_manage_staff(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $operator = $this->createUser();
        $this->attach($operator, $company, 'operator');

        $this->withHeaders($this->authHeaders($operator))
            ->postJson('/api/v1/staff', [
                'email' => 'someone@example.com',
                'role' => 'cashier',
            ])->assertForbidden();
    }
}
