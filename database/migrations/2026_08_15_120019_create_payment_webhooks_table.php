<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('payment_gateway_id')->nullable();
            $table->string('event_type')->nullable();
            $table->string('external_reference')->nullable();
            $table->json('payload');
            $table->string('status')->default('received')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'external_reference']);
            $table->foreign(['payment_gateway_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('payment_gateways')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
