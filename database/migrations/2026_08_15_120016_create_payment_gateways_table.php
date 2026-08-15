<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->text('credentials')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'provider']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
