<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id');
            $table->unsignedBigInteger('internet_plan_id');
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['network_station_id', 'internet_plan_id']);
            $table->index(['company_id', 'status']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_plans');
    }
};
