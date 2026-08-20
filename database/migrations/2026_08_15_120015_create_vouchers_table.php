<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('voucher_batch_id');
            $table->unsignedBigInteger('internet_plan_id');
            $table->string('code');
            $table->string('status')->default('available')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('access_grant_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index('expires_at');
            $table->foreign(['voucher_batch_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('voucher_batches')
                ->restrictOnDelete();
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
