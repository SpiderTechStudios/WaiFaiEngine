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

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const INCOME_VIEW = 'income.view';

    public const DEVICE_SETUP_VIEW = 'device_setup.view';

    public const ROUTERS_VIEW = 'routers.view';

    public const ROUTERS_CREATE = 'routers.create';

    public const ROUTERS_UPDATE = 'routers.update';

    public const ROUTERS_DELETE = 'routers.delete';

    public const PACKAGES_VIEW = 'packages.view';

    public const PACKAGES_CREATE = 'packages.create';

    public const PACKAGES_UPDATE = 'packages.update';

    public const PACKAGES_DELETE = 'packages.delete';

    public const VOUCHERS_VIEW = 'vouchers.view';

    public const VOUCHERS_CREATE = 'vouchers.create';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_CREATE = 'payments.create';

    public const SESSIONS_VIEW = 'sessions.view';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const BRANCHES_VIEW = 'branches.view';

    public const BRANCHES_CREATE = 'branches.create';

    public const BRANCHES_UPDATE = 'branches.update';

    public const BRANCHES_DELETE = 'branches.delete';

    public const WITHDRAWALS_VIEW = 'withdrawals.view';

    public const WITHDRAWALS_CREATE = 'withdrawals.create';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_UPDATE = 'settings.update';

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
