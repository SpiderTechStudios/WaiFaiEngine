<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captive_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_device_id');
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->string('gateway_id');
            $table->string('client_mac', 32)->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('ssid')->nullable();
            $table->string('gw_address', 45)->nullable();
            $table->unsignedInteger('gw_port')->nullable();
            $table->text('requested_url')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('access_grant_id')->nullable();
            $table->unsignedBigInteger('network_session_id')->nullable();
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['network_device_id', 'client_mac', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['gateway_id', 'status']);

            $table->foreign(['network_device_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_devices')
                ->restrictOnDelete();

            // Composite FKs that include non-nullable company_id cannot use ON DELETE SET NULL on MySQL.
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();

            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();

            $table->foreign(['network_session_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_sessions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captive_sessions');
    }
};
