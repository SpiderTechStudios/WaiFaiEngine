<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captive_portals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_station_id')->nullable();
            $table->unsignedBigInteger('network_ssid_id')->nullable();
            $table->string('name');
            $table->string('headline')->nullable();
            $table->text('welcome_message')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('background_color')->nullable();
            $table->text('terms')->nullable();
            $table->string('success_message')->nullable();
            $table->string('language')->default('en');
            $table->boolean('show_plans')->default(true);
            $table->boolean('show_vouchers')->default(true);
            $table->boolean('show_mobile_money')->default(true);
            $table->string('status')->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->foreign(['network_station_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_stations')
                ->restrictOnDelete();
            $table->foreign(['network_ssid_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_ssids')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captive_portals');
    }
};
