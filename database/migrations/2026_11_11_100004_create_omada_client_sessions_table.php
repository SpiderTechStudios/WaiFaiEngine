<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omada_client_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('omada_integration_id');
            $table->unsignedBigInteger('network_device_id')->nullable();
            $table->string('site_id', 64)->nullable();
            $table->string('client_mac', 32);
            $table->string('client_ip', 45)->nullable();
            $table->string('ap_mac', 32)->nullable();
            $table->string('ssid_name', 255)->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('access_grant_id')->nullable();
            $table->string('authorization_status', 20)->default('pending');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->string('controller_reference', 128)->nullable();
            $table->string('last_sync_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'authorization_status']);
            $table->index(['omada_integration_id', 'client_mac']);
            $table->index(['company_id', 'client_mac']);
            $table->index('payment_transaction_id');

            $table->foreign(['omada_integration_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('omada_integrations')
                ->cascadeOnDelete();

            $table->foreign(['network_device_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_devices')
                ->cascadeOnDelete();

            $table->foreign('payment_transaction_id')
                ->references('id')
                ->on('payment_transactions')
                ->nullOnDelete();

            $table->foreign('access_grant_id')
                ->references('id')
                ->on('access_grants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omada_client_sessions');
    }
};
