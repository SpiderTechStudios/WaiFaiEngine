<?php

namespace App\Support;

final class Permissions
{
    public const COMPANIES_VIEW = 'companies.view';

    public const COMPANIES_UPDATE = 'companies.update';

    public const STAFF_VIEW = 'staff.view';

    public const STAFF_CREATE = 'staff.create';

    public const STAFF_UPDATE = 'staff.update';

    public const STAFF_SUSPEND = 'staff.suspend';

    public const STAFF_DELETE = 'staff.delete';

    public const STAFF_CHANGE_ROLE = 'staff.change_role';

    public const OWNERSHIP_TRANSFER = 'ownership.transfer';

    /**
     * @return list<string>
     */
    public static function assignableBy(string $actorRoleSlug): array
    {
        return match ($actorRoleSlug) {
            'owner' => ['owner', 'manager', 'operator', 'cashier'],
            'manager' => ['manager', 'operator', 'cashier'],
            default => [],
        };
    }
}
