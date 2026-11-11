<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omada_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('omada_integration_id');
            $table->string('site_id', 64);
            $table->string('site_name', 255);
            $table->string('timezone', 64)->nullable();
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['omada_integration_id', 'site_id']);
            $table->index(['company_id', 'status']);

            $table->foreign(['omada_integration_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('omada_integrations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omada_sites');
    }
};
