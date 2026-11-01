<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A voucher code is sold once and must be usable once. Older accounts
        // created codes with a higher max_uses, which let one customer redeem
        // the same code again. Collapse every existing code to a single use.
        DB::table('vouchers')->where('max_uses', '>', 1)->update(['max_uses' => 1]);

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index(['company_id', 'uses_count'], 'vouchers_company_usage_index');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_company_usage_index');
        });
    }
};
