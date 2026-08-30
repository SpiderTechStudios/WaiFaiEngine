<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('installation_request_id')
                ->nullable()
                ->after('signup_intent_id');

            $table->foreign('installation_request_id', 'plat_pay_inst_req_fk')
                ->references('id')
                ->on('installation_requests')
                ->nullOnDelete();
        });

        Schema::table('network_devices', function (Blueprint $table) {
            $table->string('source')->nullable()->after('type');
            $table->unsignedBigInteger('installation_request_id')
                ->nullable()
                ->after('source');

            $table->foreign('installation_request_id', 'net_dev_inst_req_fk')
                ->references('id')
                ->on('installation_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropForeign('net_dev_inst_req_fk');
            $table->dropColumn(['installation_request_id', 'source']);
        });

        Schema::table('platform_payments', function (Blueprint $table) {
            $table->dropForeign('plat_pay_inst_req_fk');
            $table->dropColumn('installation_request_id');
        });
    }
};
