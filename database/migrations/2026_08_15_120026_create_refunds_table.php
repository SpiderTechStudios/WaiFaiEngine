<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->string('reference');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->foreign(['payment_transaction_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_transactions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
