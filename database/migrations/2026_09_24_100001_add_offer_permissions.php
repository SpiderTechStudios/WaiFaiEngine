<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<array{name: string, slug: string}>
     */
    private array $permissions = [
        ['name' => 'View offers', 'slug' => 'offers.view'],
        ['name' => 'Create offers', 'slug' => 'offers.create'],
        ['name' => 'Update offers', 'slug' => 'offers.update'],
        ['name' => 'Delete offers', 'slug' => 'offers.delete'],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $permission) {
            if (DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_column($this->permissions, 'slug'))
            ->pluck('id', 'slug');

        $roleIds = DB::table('roles')->whereNull('company_id')->pluck('id', 'slug');

        $map = [
            'owner' => ['offers.view', 'offers.create', 'offers.update', 'offers.delete'],
            'manager' => ['offers.view', 'offers.create', 'offers.update', 'offers.delete'],
            'operator' => ['offers.view'],
            'cashier' => ['offers.view'],
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
        $slugs = array_column($this->permissions, 'slug');
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
