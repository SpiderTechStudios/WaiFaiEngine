<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            'select is_nullable, column_default from information_schema.columns '
            .'where table_schema = database() and table_name = ? and column_name = ?',
            ['captive_sessions', 'expires_at'],
        );

        if ($column === null) {
            return;
        }

        $hasInvalidDefault = $column->is_nullable === 'NO'
            || str_starts_with((string) $column->column_default, '0000-00-00');

        if ($hasInvalidDefault) {
            DB::statement('ALTER TABLE `captive_sessions` MODIFY `expires_at` TIMESTAMP NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
    }
};
