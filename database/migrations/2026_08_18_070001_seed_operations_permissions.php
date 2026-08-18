<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['name' => 'View dashboard', 'slug' => 'dashboard.view'],
            ['name' => 'View income', 'slug' => 'income.view'],
            ['name' => 'View device setup', 'slug' => 'device_setup.view'],
            ['name' => 'View routers', 'slug' => 'routers.view'],
            ['name' => 'Create routers', 'slug' => 'routers.create'],
            ['name' => 'Update routers', 'slug' => 'routers.update'],
            ['name' => 'Delete routers', 'slug' => 'routers.delete'],
            ['name' => 'View packages', 'slug' => 'packages.view'],
            ['name' => 'Create packages', 'slug' => 'packages.create'],
            ['name' => 'Update packages', 'slug' => 'packages.update'],
            ['name' => 'Delete packages', 'slug' => 'packages.delete'],
            ['name' => 'View vouchers', 'slug' => 'vouchers.view'],
            ['name' => 'Create vouchers', 'slug' => 'vouchers.create'],
            ['name' => 'View payments', 'slug' => 'payments.view'],
            ['name' => 'Create payments', 'slug' => 'payments.create'],
            ['name' => 'View sessions', 'slug' => 'sessions.view'],
            ['name' => 'View customers', 'slug' => 'customers.view'],
            ['name' => 'View branches', 'slug' => 'branches.view'],
            ['name' => 'Create branches', 'slug' => 'branches.create'],
            ['name' => 'Update branches', 'slug' => 'branches.update'],
            ['name' => 'Delete branches', 'slug' => 'branches.delete'],
            ['name' => 'View withdrawals', 'slug' => 'withdrawals.view'],
            ['name' => 'Create withdrawals', 'slug' => 'withdrawals.create'],
            ['name' => 'View settings', 'slug' => 'settings.view'],
            ['name' => 'Update settings', 'slug' => 'settings.update'],
        ];

        foreach ($permissions as $permission) {
            if (DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->whereNull('company_id')->pluck('id', 'slug');

        $map = [
            'owner' => $permissionIds->keys()->all(),
            'manager' => [
                'companies.view',
                'companies.update',
                'staff.view',
                'staff.create',
                'staff.update',
                'staff.suspend',
                'staff.delete',
                'staff.change_role',
                'dashboard.view',
                'income.view',
                'device_setup.view',
                'routers.view',
                'routers.create',
                'routers.update',
                'routers.delete',
                'packages.view',
                'packages.create',
                'packages.update',
                'packages.delete',
                'vouchers.view',
                'vouchers.create',
                'payments.view',
                'payments.create',
                'sessions.view',
                'customers.view',
                'branches.view',
                'branches.create',
                'branches.update',
                'branches.delete',
                'withdrawals.view',
                'withdrawals.create',
                'settings.view',
                'settings.update',
            ],
            'operator' => [
                'companies.view',
                'staff.view',
                'dashboard.view',
                'device_setup.view',
                'routers.view',
                'packages.view',
                'vouchers.view',
                'vouchers.create',
                'payments.view',
                'sessions.view',
                'customers.view',
                'branches.view',
            ],
            'cashier' => [
                'companies.view',
                'dashboard.view',
                'packages.view',
                'vouchers.view',
                'vouchers.create',
                'payments.view',
                'payments.create',
                'customers.view',
            ],
        ];

        foreach ($map as $roleSlug => $slugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if (! $roleId) {
                continue;
            }

            foreach ($slugs as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;
                if (! $permissionId) {
                    continue;
                }

                $exists = DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('permission_role')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'dashboard.view',
            'income.view',
            'device_setup.view',
            'routers.view',
            'routers.create',
            'routers.update',
            'routers.delete',
            'packages.view',
            'packages.create',
            'packages.update',
            'packages.delete',
            'vouchers.view',
            'vouchers.create',
            'payments.view',
            'payments.create',
            'sessions.view',
            'customers.view',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'withdrawals.view',
            'withdrawals.create',
            'settings.view',
            'settings.update',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
