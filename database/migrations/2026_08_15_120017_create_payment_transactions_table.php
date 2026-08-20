<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('internet_plan_id')->nullable();
            $table->unsignedBigInteger('payment_gateway_id')->nullable();
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->string('reference');
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('payment_method');
            $table->string('status')->default('pending')->index();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'external_reference']);
            $table->index('created_at');
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
            $table->foreign(['payment_gateway_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_gateways')
                ->restrictOnDelete();
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
            $table->foreign(['voucher_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('vouchers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
