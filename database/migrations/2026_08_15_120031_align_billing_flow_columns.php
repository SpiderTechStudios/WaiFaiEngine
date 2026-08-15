<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'created_by')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'current_company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('current_company_id')->nullable()->constrained('companies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('network_connections', 'network_station_id')) {
            Schema::table('network_connections', function (Blueprint $table) {
                $table->unsignedBigInteger('network_station_id')->nullable();
                $table->foreign(['network_station_id', 'company_id'])
                    ->references(['id', 'company_id'])
                    ->on('network_stations')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('voucher_batches', 'unit_price')) {
            Schema::table('voucher_batches', function (Blueprint $table) {
                $table->unsignedBigInteger('network_station_id')->nullable();
                $table->decimal('unit_price', 12, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->foreign(['network_station_id', 'company_id'])
                    ->references(['id', 'company_id'])
                    ->on('network_stations')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('payment_transactions', 'network_station_id')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('network_station_id')->nullable();
                $table->unsignedBigInteger('voucher_id')->nullable();
                $table->foreign(['network_station_id', 'company_id'])
                    ->references(['id', 'company_id'])
                    ->on('network_stations')
                    ->restrictOnDelete();
                $table->foreign(['voucher_id', 'company_id'])
                    ->references(['id', 'company_id'])
                    ->on('vouchers')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('access_grants', 'source')) {
            Schema::table('access_grants', function (Blueprint $table) {
                $table->string('source')->default('mobile_money');
                $table->index(['company_id', 'source']);
            });
        }
    }

    public function down(): void
    {
        // Columns remain on the original create migrations for new installs.
    }
};
