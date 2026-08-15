<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserCompany;
use App\Support\Permissions;

class UserCompanyPolicy
{
    public function view(User $user, UserCompany $membership): bool
    {
        return $user->is_superadmin
            || $user->hasCompanyPermission(Permissions::STAFF_VIEW, $membership->company_id);
    }

    public function update(User $user, UserCompany $membership): bool
    {
        return $user->is_superadmin
            || $user->hasCompanyPermission(Permissions::STAFF_UPDATE, $membership->company_id);
    }

    public function delete(User $user, UserCompany $membership): bool
    {
        return $user->is_superadmin
            || $user->hasCompanyPermission(Permissions::STAFF_DELETE, $membership->company_id);
    }
}
