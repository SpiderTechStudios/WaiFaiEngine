<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omada_access_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('omada_integration_id');
            $table->string('site_id', 64);
            $table->string('device_id', 128)->nullable();
            $table->string('device_name', 255)->nullable();
            $table->string('mac_address', 32)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('firmware_version', 128)->nullable();
            $table->string('online_status', 20)->default('unknown');
            $table->boolean('selected_for_waifai')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['omada_integration_id', 'device_id']);
            $table->index(['company_id', 'site_id']);
            $table->index(['company_id', 'online_status']);

            $table->foreign(['omada_integration_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('omada_integrations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omada_access_points');
    }
};
