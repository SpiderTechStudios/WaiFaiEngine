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
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
