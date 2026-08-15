<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('wallet_id');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('payment_gateway_id')->nullable();
            $table->string('provider');
            $table->string('destination_phone');
            $table->string('destination_name')->nullable();
            $table->string('reference');
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'provider']);
            $table->foreign('wallet_id')->references('id')->on('wallets')->restrictOnDelete();
            $table->foreign(['payment_gateway_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_gateways')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
