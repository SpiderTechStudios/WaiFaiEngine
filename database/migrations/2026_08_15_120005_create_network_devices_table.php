<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id');
            $table->string('type')->default('router');
            $table->string('gateway_type');
            $table->string('name');
            $table->string('lan_ip', 45)->nullable();
            $table->string('api_host')->nullable();
            $table->unsignedInteger('api_port')->nullable();
            $table->string('api_username')->nullable();
            $table->text('api_password')->nullable();
            $table->string('gateway_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedInteger('wifidog_port')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'gateway_type']);
            $table->index(['company_id', 'gateway_id']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_devices');
    }
};
