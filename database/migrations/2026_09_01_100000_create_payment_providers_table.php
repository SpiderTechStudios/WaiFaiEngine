<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('supports_payments')->default(true);
            $table->boolean('supports_payouts')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default_for_payments')->default(false)->index();
            $table->boolean('is_default_for_payouts')->default(false)->index();
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
