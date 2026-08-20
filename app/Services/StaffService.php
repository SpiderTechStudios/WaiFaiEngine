<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;
use App\Notifications\StaffInvitedNotification;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function add(User $actor, Company $company, array $data): UserCompany
    {
        if (! $actor->canAssignRole($data['role'], $company)) {
            abort(403, 'You cannot assign this role.');
        }

        $role = Role::query()->whereNull('company_id')->where('slug', $data['role'])->firstOrFail();

        return DB::transaction(function () use ($actor, $company, $data, $role) {
            $existingUser = User::query()->where('email', $data['email'])->first();
            $invited = false;

            if ($existingUser) {
                $user = $existingUser;
            } else {
                $name = trim((string) ($data['name'] ?? ''));
                $parts = $name === '' ? [] : preg_split('/\s+/', $name, 2);

                $user = User::query()->create([
                    'first_name' => $data['first_name'] ?? ($parts[0] ?? Str::before($data['email'], '@')),
                    'last_name' => $data['last_name'] ?? ($parts[1] ?? ''),
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Str::password(16),
                    'status' => 'pending',
                ]);
                $invited = true;
            }

            $membership = UserCompany::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership && $membership->status !== 'removed') {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member of the company.'],
                ]);
            }

            if ($membership) {
                $membership->fill([
                    'role_id' => $role->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ])->save();
            } else {
                $membership = UserCompany::query()->create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            if ($invited) {
                $user->notify(new StaffInvitedNotification($company, $actor));
                $this->auditLogger->log('staff_invited', $actor, $company->id, UserCompany::class, $membership->id);
            } else {
                $this->auditLogger->log('staff_added', $actor, $company->id, UserCompany::class, $membership->id);
            }

            return $membership->load(['user', 'role.permissions', 'company']);
        });
    }

    public function updateRole(User $actor, UserCompany $membership, string $roleSlug): UserCompany
    {
        $this->guardOwner($membership);

        if (! $actor->canAssignRole($roleSlug, $membership->company_id)) {
            abort(403, 'You cannot assign this role.');
        }

        if ($roleSlug === 'owner' && $membership->role?->slug !== 'owner') {
            abort(403, 'Ownership must be transferred through the ownership transfer endpoint.');
        }

        $role = Role::query()->whereNull('company_id')->where('slug', $roleSlug)->firstOrFail();
        $old = $membership->role?->slug;

        $membership->forceFill(['role_id' => $role->id])->save();

        $this->auditLogger->log(
            'staff_role_changed',
            $actor,
            $membership->company_id,
            UserCompany::class,
            $membership->id,
            ['role' => $old],
            ['role' => $roleSlug],
        );

        return $membership->load(['user', 'role.permissions']);
    }

    public function suspend(User $actor, UserCompany $membership): UserCompany
    {
        $this->guardOwner($membership);

        $membership->forceFill(['status' => 'suspended'])->save();

        $this->clearCurrentCompanyIfNeeded($membership);

        $this->auditLogger->log('staff_suspended', $actor, $membership->company_id, UserCompany::class, $membership->id);

        return $membership->load(['user', 'role']);
    }

    public function activate(User $actor, UserCompany $membership): UserCompany
    {
        $membership->forceFill(['status' => 'active'])->save();

        $this->auditLogger->log('staff_activated', $actor, $membership->company_id, UserCompany::class, $membership->id);

        return $membership->load(['user', 'role']);
    }

    public function remove(User $actor, UserCompany $membership): void
    {
        $this->guardOwner($membership);

        $membership->forceFill(['status' => 'removed'])->save();
        $this->clearCurrentCompanyIfNeeded($membership);

        $this->auditLogger->log('staff_removed', $actor, $membership->company_id, UserCompany::class, $membership->id);
    }

    public function transferOwnership(User $actor, Company $company, UserCompany $newOwnerMembership): void
    {
        if (! $actor->is_superadmin && ! $actor->hasCompanyPermission(Permissions::OWNERSHIP_TRANSFER, $company)) {
            abort(403, 'You cannot transfer ownership.');
        }

        if ($newOwnerMembership->company_id !== $company->id || $newOwnerMembership->status === 'removed') {
            abort(422, 'The selected member does not belong to this company.');
        }

        DB::transaction(function () use ($actor, $company, $newOwnerMembership) {
            $ownerRole = Role::query()->whereNull('company_id')->where('slug', 'owner')->firstOrFail();
            $managerRole = Role::query()->whereNull('company_id')->where('slug', 'manager')->firstOrFail();

            UserCompany::query()
                ->where('company_id', $company->id)
                ->where('role_id', $ownerRole->id)
                ->where('status', '!=', 'removed')
                ->update(['role_id' => $managerRole->id]);

            $newOwnerMembership->forceFill([
                'role_id' => $ownerRole->id,
                'status' => 'active',
            ])->save();

            $this->auditLogger->log(
                'ownership_transferred',
                $actor,
                $company->id,
                UserCompany::class,
                $newOwnerMembership->id,
            );
        });
    }

    private function guardOwner(UserCompany $membership): void
    {
        if ($membership->role?->slug === 'owner' || $membership->role()->value('slug') === 'owner') {
            abort(403, 'Company owners cannot be modified through staff management.');
        }
    }

    private function clearCurrentCompanyIfNeeded(UserCompany $membership): void
    {
        User::query()
            ->where('id', $membership->user_id)
            ->where('current_company_id', $membership->company_id)
            ->update(['current_company_id' => null]);
    }
}
