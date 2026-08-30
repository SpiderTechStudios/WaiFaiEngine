<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('setup_type')->nullable()->after('status');
            $table->timestamp('installation_paid_at')->nullable()->after('setup_type');
            $table->string('subscription_status')->nullable()->after('installation_paid_at')->index();
            $table->timestamp('activated_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_period_ends_at')->nullable()->after('activated_at');
        });

        // Grandfather existing tenants so they keep working after pay-first launch.
        DB::table('companies')->whereNull('subscription_status')->update([
            'subscription_status' => 'active',
            'activated_at' => DB::raw('COALESCE(activated_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'setup_type',
                'installation_paid_at',
                'subscription_status',
                'activated_at',
                'subscription_period_ends_at',
            ]);
        });
    }
};
