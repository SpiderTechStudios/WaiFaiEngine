<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_ssids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id');
            $table->unsignedBigInteger('network_device_id')->nullable();
            $table->string('name');
            $table->string('ssid');
            $table->string('authentication_type')->nullable();
            $table->boolean('captive_portal_enabled')->default(true);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'network_station_id', 'ssid'], 'network_ssids_company_station_ssid_unique');
            $table->index(['company_id', 'status']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
            $table->foreign(['network_device_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_devices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_ssids');
    }
};
