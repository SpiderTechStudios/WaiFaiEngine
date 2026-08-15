<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('network_session_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('access_grant_id');
            $table->unsignedBigInteger('upload_bytes')->default(0);
            $table->unsignedBigInteger('download_bytes')->default(0);
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'recorded_at']);
            $table->index('network_session_id');
            $table->index('access_grant_id');
            $table->foreign(['network_session_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_sessions')
                ->restrictOnDelete();
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_usage');
    }
};
