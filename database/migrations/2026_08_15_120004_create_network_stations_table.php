<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->foreign(['location_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('locations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_stations');
    }
};
