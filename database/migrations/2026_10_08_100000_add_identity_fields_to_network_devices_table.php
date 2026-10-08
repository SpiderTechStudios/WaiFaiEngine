<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->string('model')->nullable()->after('gateway_type');
            $table->string('firmware')->nullable()->after('serial_number');
            $table->string('mac_address', 32)->nullable()->after('firmware');
        });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropColumn(['model', 'firmware', 'mac_address']);
        });
    }
};
