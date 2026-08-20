<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Company $company, array $data, User $actor): VoucherBatch
    {
        $plan = InternetPlan::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['internet_plan_id']);

        $price = $plan->prices()->where('status', 'active')->latest('id')->first();
        $digits = (int) $company->voucher_code_digits;
        $digits = max(4, min(12, $digits));
        $quantity = (int) $data['quantity'];

        return DB::transaction(function () use ($company, $plan, $price, $digits, $quantity, $data, $actor) {
            $batch = VoucherBatch::query()->create([
                'company_id' => $company->id,
                'internet_plan_id' => $plan->id,
                'network_station_id' => $data['network_station_id'] ?? null,
                'name' => $data['name'] ?? $plan->name.' vouchers',
                'quantity' => $quantity,
                'unit_price' => $price?->amount,
                'currency' => $price?->currency ?? 'TZS',
                'created_by' => $actor->id,
                'status' => 'active',
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                Voucher::query()->create([
                    'company_id' => $company->id,
                    'voucher_batch_id' => $batch->id,
                    'internet_plan_id' => $plan->id,
                    'code' => $this->uniqueCode($company->id, $digits),
                    'status' => 'available',
                    'expires_at' => $data['expires_at'] ?? null,
                ]);
            }

            $this->auditLogger->log('vouchers_created', $actor, $company->id, VoucherBatch::class, $batch->id, newValues: [
                'quantity' => $quantity,
            ]);

            return $batch->load(['internetPlan', 'vouchers']);
        });
    }

    private function uniqueCode(int $companyId, int $digits): string
    {
        do {
            $max = (10 ** $digits) - 1;
            $code = str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
        } while (Voucher::query()->where('company_id', $companyId)->where('code', $code)->exists());

        return $code;
    }
}
