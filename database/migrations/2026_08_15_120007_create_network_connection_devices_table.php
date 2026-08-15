<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_connection_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('network_connection_id');
            $table->unsignedBigInteger('network_device_id');
            $table->string('external_id');
            $table->string('external_serial_number')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['network_connection_id', 'network_device_id'], 'ncd_connection_device_unique');
            $table->index('external_id');
            $table->foreign('network_connection_id')->references('id')->on('network_connections')->restrictOnDelete();
            $table->foreign('network_device_id')->references('id')->on('network_devices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_connection_devices');
    }
};
