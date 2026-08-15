<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('access_grant_id')->nullable()->after('customer_id');
            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['access_grant_id', 'company_id']);
            $table->dropColumn('access_grant_id');
        });
    }
};
