<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
