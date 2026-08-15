<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['name' => 'View company', 'slug' => 'companies.view'],
            ['name' => 'Update company', 'slug' => 'companies.update'],
            ['name' => 'View staff', 'slug' => 'staff.view'],
            ['name' => 'Create staff', 'slug' => 'staff.create'],
            ['name' => 'Update staff', 'slug' => 'staff.update'],
            ['name' => 'Suspend staff', 'slug' => 'staff.suspend'],
            ['name' => 'Delete staff', 'slug' => 'staff.delete'],
            ['name' => 'Change staff role', 'slug' => 'staff.change_role'],
            ['name' => 'Transfer ownership', 'slug' => 'ownership.transfer'],
        ];

        foreach ($permissions as $permission) {
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
            ],
            'operator' => ['companies.view', 'staff.view'],
            'cashier' => ['companies.view'],
        ];

        foreach ($map as $roleSlug => $slugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if (! $roleId) {
                continue;
            }

            foreach ($slugs as $slug) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permissionIds[$slug],
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->delete();
        DB::table('permissions')->delete();
    }
};
