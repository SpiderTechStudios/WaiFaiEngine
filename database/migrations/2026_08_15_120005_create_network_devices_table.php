<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id');
            $table->string('type');
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'mac_address']);
            $table->index(['company_id', 'serial_number']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_devices');
    }
};
