<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_id', 'role_id']);
        });

        $now = now();

        DB::table('roles')->insert([
            ['company_id' => null, 'name' => 'Owner', 'slug' => 'owner', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['company_id' => null, 'name' => 'Manager', 'slug' => 'manager', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['company_id' => null, 'name' => 'Operator', 'slug' => 'operator', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['company_id' => null, 'name' => 'Cashier', 'slug' => 'cashier', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

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
            ['name' => 'View dashboard', 'slug' => 'dashboard.view'],
            ['name' => 'View income', 'slug' => 'income.view'],
            ['name' => 'View device setup', 'slug' => 'device_setup.view'],
            ['name' => 'Create device setup', 'slug' => 'device_setup.create'],
            ['name' => 'Update device setup', 'slug' => 'device_setup.update'],
            ['name' => 'View routers', 'slug' => 'routers.view'],
            ['name' => 'Create routers', 'slug' => 'routers.create'],
            ['name' => 'Update routers', 'slug' => 'routers.update'],
            ['name' => 'Delete routers', 'slug' => 'routers.delete'],
            ['name' => 'Sync routers', 'slug' => 'routers.sync'],
            ['name' => 'View packages', 'slug' => 'packages.view'],
            ['name' => 'Create packages', 'slug' => 'packages.create'],
            ['name' => 'Update packages', 'slug' => 'packages.update'],
            ['name' => 'Delete packages', 'slug' => 'packages.delete'],
            ['name' => 'View vouchers', 'slug' => 'vouchers.view'],
            ['name' => 'Create vouchers', 'slug' => 'vouchers.create'],
            ['name' => 'Revoke vouchers', 'slug' => 'vouchers.revoke'],
            ['name' => 'Consume vouchers', 'slug' => 'vouchers.consume'],
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
                'device_setup.create',
                'device_setup.update',
                'routers.view',
                'routers.create',
                'routers.update',
                'routers.delete',
                'routers.sync',
                'packages.view',
                'packages.create',
                'packages.update',
                'packages.delete',
                'vouchers.view',
                'vouchers.create',
                'vouchers.revoke',
                'vouchers.consume',
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
                'vouchers.revoke',
                'vouchers.consume',
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
                'vouchers.revoke',
                'vouchers.consume',
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
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
