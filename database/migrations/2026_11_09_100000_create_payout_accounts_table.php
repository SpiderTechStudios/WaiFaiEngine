<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('network')->nullable()->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->unique(['company_id', 'phone']);
            $table->index(['company_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
