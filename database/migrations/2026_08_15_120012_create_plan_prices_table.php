<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('internet_plan_id');
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('active')->index();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['internet_plan_id', 'status', 'valid_from']);
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
