<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return Collection<int, Voucher>
     */
    public function create(Company $company, array $data, User $actor): Collection
    {
        $router = NetworkDevice::query()
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->findOrFail($data['router_id']);

        $plan = InternetPlan::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['package_id']);

        $quantity = (int) $data['quantity'];
        $maxUses = (int) ($data['max_uses'] ?? 1);
        $digits = max(4, min(12, (int) $company->voucher_code_digits));
        $customCode = $data['custom_code'] ?? null;

        if (filled($customCode)
            && Voucher::query()->where('company_id', $company->id)->where('code', $customCode)->exists()
        ) {
            throw ValidationException::withMessages([
                'custom_code' => ['This voucher code is already in use.'],
            ]);
        }

        return DB::transaction(function () use ($company, $router, $plan, $quantity, $maxUses, $digits, $customCode, $data, $actor) {
            $vouchers = new Collection;

            for ($i = 0; $i < $quantity; $i++) {
                $code = $quantity === 1 && filled($customCode)
                    ? $customCode
                    : $this->uniqueCode($company->id, $digits);

                $vouchers->push(Voucher::query()->create([
                    'company_id' => $company->id,
                    'network_device_id' => $router->id,
                    'internet_plan_id' => $plan->id,
                    'code' => $code,
                    'max_uses' => $maxUses,
                    'uses_count' => 0,
                    'expires_at' => $data['expires_at'] ?? null,
                    'note' => $data['note'] ?? null,
                    'status' => Voucher::STATUS_ACTIVE,
                    'created_by' => $actor->id,
                ]));
            }

            $this->auditLogger->log(
                'vouchers_created',
                $actor,
                $company->id,
                Voucher::class,
                $vouchers->first()?->id,
                newValues: [
                    'quantity' => $quantity,
                    'router_id' => $router->id,
                    'package_id' => $plan->id,
                ],
            );

            return $vouchers->load(['router', 'internetPlan']);
        });
    }

    public function revoke(Voucher $voucher, User $actor): Voucher
    {
        $voucher->syncExpiryStatus();

        if ($voucher->status === Voucher::STATUS_REVOKED) {
            throw ValidationException::withMessages([
                'voucher' => ['This voucher is already revoked.'],
            ]);
        }

        if ($voucher->status === Voucher::STATUS_EXPIRED) {
            throw ValidationException::withMessages([
                'voucher' => ['Expired vouchers cannot be revoked.'],
            ]);
        }

        $voucher->forceFill([
            'status' => Voucher::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();

        $this->auditLogger->log('voucher_revoked', $actor, $voucher->company_id, Voucher::class, $voucher->id);

        return $voucher->load(['router', 'internetPlan']);
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
