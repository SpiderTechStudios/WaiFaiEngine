<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_sessions', function (Blueprint $table) {
            $table->string('router_username', 64)->nullable()->after('session_id');
            $table->string('router_active_id', 64)->nullable()->after('router_username');
        });
    }

    public function down(): void
    {
        Schema::table('network_sessions', function (Blueprint $table) {
            $table->dropColumn(['router_username', 'router_active_id']);
        });
    }
};
