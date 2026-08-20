<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('internet_plan_id');
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->string('source');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('data_limit')->nullable();
            $table->unsignedBigInteger('data_used')->default(0);
            $table->unsignedInteger('max_devices')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'customer_id', 'status']);
            $table->index('expires_at');
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
            $table->foreign(['payment_transaction_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_transactions')
                ->restrictOnDelete();
            $table->foreign(['voucher_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('vouchers')
                ->restrictOnDelete();
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['access_grant_id', 'company_id']);
        });

        Schema::dropIfExists('access_grants');
    }
};
