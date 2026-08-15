<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('internet_plan_id');
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_batches');
    }
};
