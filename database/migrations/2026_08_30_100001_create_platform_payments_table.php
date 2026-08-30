<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_payments', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->uuid('signup_intent_id')->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('external_reference')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('payment_method')->default('mobile_money');
            $table->string('phone', 50)->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('signup_intent_id')
                ->references('id')
                ->on('signup_intents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
    }
};
