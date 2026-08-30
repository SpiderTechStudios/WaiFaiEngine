<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['name' => 'View installation requests', 'slug' => 'installation_requests.view'],
            ['name' => 'Create installation requests', 'slug' => 'installation_requests.create'],
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')->where('slug', $permission['slug'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'installation_requests.view',
                'installation_requests.create',
            ])
            ->pluck('id', 'slug');

        $roleIds = DB::table('roles')->whereNull('company_id')->pluck('id', 'slug');

        $map = [
            'owner' => ['installation_requests.view', 'installation_requests.create'],
            'manager' => ['installation_requests.view', 'installation_requests.create'],
            'operator' => ['installation_requests.view'],
            'cashier' => ['installation_requests.view'],
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
        $slugs = ['installation_requests.view', 'installation_requests.create'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
