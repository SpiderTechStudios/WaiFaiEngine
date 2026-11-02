<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->string('portal_key', 64)->nullable()->after('wifidog_port');
            $table->unsignedSmallInteger('hotspot_port')->nullable()->default(80)->after('portal_key');
            $table->text('callback_secret')->nullable()->after('hotspot_port');
            $table->boolean('api_verify_tls')->default(true)->after('callback_secret');
            $table->string('connection_mode', 20)->default('public')->after('api_verify_tls');
            $table->string('router_identity', 100)->nullable()->after('connection_mode');
            $table->timestamp('last_synced_at')->nullable()->after('last_seen_at');

            $table->unique('portal_key');
        });

        // Existing MikroTik routers need a portal key before their login page
        // can create a captive session.
        DB::table('network_devices')
            ->where('gateway_type', 'mikrotik')
            ->whereNull('portal_key')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $router): void {
                $update = ['portal_key' => Str::random(48)];

                try {
                    $update['callback_secret'] = Crypt::encryptString(Str::random(48));
                } catch (Throwable) {
                    // APP_KEY is unavailable; leave the optional callback off.
                }

                DB::table('network_devices')->where('id', $router->id)->update($update);
            });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropUnique(['portal_key']);
            $table->dropColumn([
                'portal_key',
                'hotspot_port',
                'callback_secret',
                'api_verify_tls',
                'connection_mode',
                'router_identity',
                'last_synced_at',
            ]);
        });
    }
};
