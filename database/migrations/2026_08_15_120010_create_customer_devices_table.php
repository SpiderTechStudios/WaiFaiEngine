<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('name')->nullable();
            $table->string('device_type')->nullable();
            $table->string('mac_address');
            $table->string('status')->default('active')->index();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'mac_address']);
            $table->index(['company_id', 'status']);
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
    }
};
