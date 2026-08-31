<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signup_intents', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->after('id');
            $table->unsignedTinyInteger('failed_payment_attempts')->default(0)->after('status');
        });

        foreach (DB::table('signup_intents')->whereNull('reference')->get() as $row) {
            DB::table('signup_intents')->where('id', $row->id)->update([
                'reference' => 'ENR-'.strtoupper(Str::random(9)),
            ]);
        }

        Schema::table('signup_intents', function (Blueprint $table) {
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('signup_intents', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'failed_payment_attempts']);
        });
    }
};
