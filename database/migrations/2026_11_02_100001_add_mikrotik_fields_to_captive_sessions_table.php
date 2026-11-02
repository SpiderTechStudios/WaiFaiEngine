<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captive_sessions', function (Blueprint $table) {
            $table->string('mikrotik_username', 64)->nullable()->after('gw_port');
            $table->text('mikrotik_password')->nullable()->after('mikrotik_username');
            $table->string('mikrotik_user_id', 64)->nullable()->after('mikrotik_password');
            $table->unsignedBigInteger('mikrotik_grant_id')->nullable()->after('mikrotik_user_id');
            $table->timestamp('mikrotik_provisioned_at')->nullable()->after('mikrotik_grant_id');
            $table->string('router_active_id', 64)->nullable()->after('mikrotik_provisioned_at');
            $table->timestamp('router_authorized_at')->nullable()->after('router_active_id');
            $table->timestamp('router_last_seen_at')->nullable()->after('router_authorized_at');

            $table->index(['network_device_id', 'mikrotik_username'], 'captive_sessions_device_user_index');
            $table->index(['network_device_id', 'router_active_id'], 'captive_sessions_device_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('captive_sessions', function (Blueprint $table) {
            $table->dropIndex('captive_sessions_device_user_index');
            $table->dropIndex('captive_sessions_device_active_index');
            $table->dropColumn([
                'mikrotik_username',
                'mikrotik_password',
                'mikrotik_user_id',
                'mikrotik_grant_id',
                'mikrotik_provisioned_at',
                'router_active_id',
                'router_authorized_at',
                'router_last_seen_at',
            ]);
        });
    }
};
