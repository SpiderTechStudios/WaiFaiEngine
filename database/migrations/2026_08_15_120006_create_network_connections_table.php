<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->string('provider');
            $table->string('name');
            $table->text('credentials')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'provider']);
            $table->index(['company_id', 'status']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_connections');
    }
};
