<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internet_plans', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('status');
            $table->boolean('visible_on_portal')->default(true)->after('status');
            $table->unsignedSmallInteger('speed_download_mbps')->nullable()->after('price');
            $table->unsignedSmallInteger('speed_upload_mbps')->nullable()->after('speed_download_mbps');
            $table->unsignedInteger('data_cap_mb')->nullable()->after('speed_upload_mbps');
            $table->unsignedSmallInteger('devices_allowed')->nullable()->after('data_cap_mb');

            $table->index(['company_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('internet_plans', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'sort_order']);
            $table->dropColumn([
                'sort_order',
                'visible_on_portal',
                'speed_download_mbps',
                'speed_upload_mbps',
                'data_cap_mb',
                'devices_allowed',
            ]);
        });
    }
};
