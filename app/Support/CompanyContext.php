<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;

class CompanyContext
{
    public function __construct(
        public ?User $user = null,
        public ?Company $company = null,
        public ?UserCompany $membership = null,
        public ?Role $role = null,
    ) {}

    public function hasPermission(string $permission): bool
    {
        if ($this->user?->is_superadmin) {
            return true;
        }

        if (! $this->role) {
            return false;
        }

        $this->role->loadMissing('permissions');

        return $this->role->permissions->contains('slug', $permission);
    }

    public function isOwner(): bool
    {
        return $this->role?->slug === 'owner';
    }

    public function isActive(): bool
    {
        return $this->company?->status === 'active'
            && $this->membership?->status === 'active';
    }
}
