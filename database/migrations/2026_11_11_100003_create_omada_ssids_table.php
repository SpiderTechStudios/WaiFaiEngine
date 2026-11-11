<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omada_ssids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('omada_integration_id');
            $table->string('site_id', 64);
            $table->string('wlan_id', 128)->nullable();
            $table->string('ssid_name', 255);
            $table->string('status', 20)->default('active');
            $table->boolean('portal_enabled')->default(false);
            $table->boolean('selected_for_waifai')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['omada_integration_id', 'wlan_id']);
            $table->index(['company_id', 'site_id']);
            $table->index(['company_id', 'status']);

            $table->foreign(['omada_integration_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('omada_integrations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omada_ssids');
    }
};
