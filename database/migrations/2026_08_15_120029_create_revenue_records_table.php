<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->string('source');
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->unsignedBigInteger('access_grant_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->default('recognized')->index();
            $table->timestamp('recognized_at')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'recognized_at']);
            $table->index(['company_id', 'status']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->restrictOnDelete();
            $table->foreign(['payment_transaction_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_transactions')
                ->restrictOnDelete();
            $table->foreign(['voucher_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('vouchers')
                ->restrictOnDelete();
            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_records');
    }
};
