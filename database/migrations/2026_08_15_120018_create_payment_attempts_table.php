<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->string('provider_reference')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending')->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('provider_reference');
            $table->foreign(['payment_transaction_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_transactions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
