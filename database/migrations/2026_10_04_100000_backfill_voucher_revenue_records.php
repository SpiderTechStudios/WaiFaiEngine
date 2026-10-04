<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vouchers redeemed before voucher revenue was recognized: record one sale
     * per voucher at its package price, dated at its first redemption.
     */
    public function up(): void
    {
        $vouchers = DB::table('vouchers')
            ->join('internet_plans', 'internet_plans.id', '=', 'vouchers.internet_plan_id')
            ->leftJoin('network_devices', 'network_devices.id', '=', 'vouchers.network_device_id')
            ->where('vouchers.uses_count', '>', 0)
            ->where('internet_plans.price', '>', 0)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('revenue_records')
                    ->whereColumn('revenue_records.voucher_id', 'vouchers.id');
            })
            ->get([
                'vouchers.id',
                'vouchers.company_id',
                'vouchers.code',
                'vouchers.updated_at',
                'internet_plans.price',
                'network_devices.network_station_id',
            ]);

        foreach ($vouchers as $voucher) {
            $firstGrant = DB::table('access_grants')
                ->where('voucher_id', $voucher->id)
                ->orderBy('id')
                ->first(['id', 'created_at']);

            $recognizedAt = $firstGrant->created_at ?? $voucher->updated_at ?? now();

            DB::table('revenue_records')->insert([
                'company_id' => $voucher->company_id,
                'network_station_id' => $voucher->network_station_id,
                'wallet_id' => null,
                'source' => 'voucher',
                'voucher_id' => $voucher->id,
                'access_grant_id' => $firstGrant->id ?? null,
                'amount' => $voucher->price,
                'currency' => config('platform.currency', 'TZS'),
                'status' => 'recognized',
                'recognized_at' => $recognizedAt,
                'description' => 'Voucher '.$voucher->code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Backfilled rows are indistinguishable from live voucher sales; keep them.
    }
};
