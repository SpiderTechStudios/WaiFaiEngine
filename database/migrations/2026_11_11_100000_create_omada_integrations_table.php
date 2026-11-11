<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omada_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_device_id')->nullable();
            $table->string('name');
            $table->string('deployment_type', 20)->default('software');
            $table->string('controller_url', 255);
            $table->string('controller_host', 255);
            $table->unsignedSmallInteger('controller_port')->default(8043);
            $table->string('controller_id', 64)->nullable();
            $table->string('controller_version', 64)->nullable();
            $table->string('connection_method', 20)->default('public');
            $table->string('portal_key', 64)->unique();
            $table->text('credentials');
            $table->boolean('verify_tls')->default(true);
            $table->string('site_id', 64)->nullable();
            $table->string('site_name', 255)->nullable();
            $table->string('site_timezone', 64)->nullable();
            $table->string('connection_status', 30)->default('unknown');
            $table->string('status', 20)->default('active');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'connection_status']);

            $table->foreign(['network_device_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_devices')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omada_integrations');
    }
};
