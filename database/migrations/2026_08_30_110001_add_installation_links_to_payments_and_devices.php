<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            $table->foreignId('installation_request_id')
                ->nullable()
                ->after('signup_intent_id')
                ->constrained('installation_requests')
                ->nullOnDelete();
        });

        Schema::table('network_devices', function (Blueprint $table) {
            $table->string('source')->nullable()->after('type');
            $table->foreignId('installation_request_id')
                ->nullable()
                ->after('source')
                ->constrained('installation_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installation_request_id');
            $table->dropColumn('source');
        });

        Schema::table('platform_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installation_request_id');
        });
    }
};
