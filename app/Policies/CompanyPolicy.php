<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $this->member($user, $company) || $user->is_superadmin;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->is_superadmin || $user->hasCompanyPermission(Permissions::COMPANIES_UPDATE, $company);
    }

    public function manageStaff(User $user, Company $company): bool
    {
        return $user->is_superadmin || $user->hasCompanyPermission(Permissions::STAFF_VIEW, $company);
    }

    private function member(User $user, Company $company): bool
    {
        $membership = $user->membershipFor($company);

        return $membership && $membership->status === 'active';
    }
}
