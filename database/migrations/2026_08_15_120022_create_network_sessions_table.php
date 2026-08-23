<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('customer_device_id')->nullable();
            $table->unsignedBigInteger('access_grant_id');
            $table->unsignedBigInteger('internet_plan_id');
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->unsignedBigInteger('network_device_id')->nullable();
            $table->unsignedBigInteger('network_ssid_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'session_id']);
            $table->index(['company_id', 'mac_address']);
            $table->index(['company_id', 'internet_plan_id']);
            $table->index(['company_id', 'payment_transaction_id']);
            $table->index('access_grant_id');
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['customer_device_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customer_devices')
                ->restrictOnDelete();
            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
            $table->foreign(['payment_transaction_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_transactions')
                ->restrictOnDelete();
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
            $table->foreign(['network_device_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_devices')
                ->restrictOnDelete();
            $table->foreign(['network_ssid_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_ssids')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_sessions');
    }
};
