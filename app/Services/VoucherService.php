<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
        $router = $this->resolveRouter($company, (int) $data['router_id']);
        $plan = $this->resolvePackage($company, (int) $data['package_id']);

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

    /**
     * @param  array<string, mixed>  $data
     * @return array{voucher: Voucher, access_grant: AccessGrant, customer: Customer}
     */
    public function consume(Voucher $voucher, array $data, User $actor): array
    {
        $voucher->syncExpiryStatus();
        $voucher->loadMissing('internetPlan');

        if ($voucher->status !== Voucher::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'voucher' => ['Only active vouchers can be consumed.'],
            ]);
        }

        if ($voucher->uses_count >= $voucher->max_uses) {
            throw ValidationException::withMessages([
                'voucher' => ['This voucher has no remaining uses.'],
            ]);
        }

        $plan = $voucher->internetPlan;
        if (! $plan) {
            throw ValidationException::withMessages([
                'voucher' => ['This voucher is not linked to a package.'],
            ]);
        }

        return DB::transaction(function () use ($voucher, $plan, $data, $actor) {
            $customer = $this->resolveCustomer($voucher->company_id, $data);

            $startsAt = now();
            $expiresAt = $this->expiresAtForPlan($plan, $startsAt);

            $grant = AccessGrant::query()->create([
                'company_id' => $voucher->company_id,
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'voucher_id' => $voucher->id,
                'source' => 'voucher',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            $voucher->forceFill([
                'uses_count' => $voucher->uses_count + 1,
                'customer_id' => $customer->id,
                'access_grant_id' => $grant->id,
            ])->save();

            $this->auditLogger->log('voucher_consumed', $actor, $voucher->company_id, Voucher::class, $voucher->id);

            return [
                'voucher' => $voucher->fresh()->load(['router', 'internetPlan']),
                'access_grant' => $grant,
                'customer' => $customer,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(int $companyId, array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()
                ->where('company_id', $companyId)
                ->findOrFail($data['customer_id']);
        }

        if (! empty($data['customer_phone'])) {
            $existing = Customer::query()
                ->where('company_id', $companyId)
                ->where('phone', $data['customer_phone'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Customer::query()->create([
            'company_id' => $companyId,
            'name' => $data['customer_name'] ?? 'Voucher guest',
            'phone' => $data['customer_phone'] ?? null,
            'email' => $data['customer_email'] ?? null,
            'status' => 'active',
        ]);
    }

    private function expiresAtForPlan(InternetPlan $plan, Carbon $startsAt): ?Carbon
    {
        if ($plan->duration_unit === InternetPlan::DURATION_UNITS[4] || blank($plan->duration)) {
            return null;
        }

        return match ($plan->duration_unit) {
            'HOURS' => $startsAt->copy()->addHours((int) $plan->duration),
            'DAYS' => $startsAt->copy()->addDays((int) $plan->duration),
            'WEEKS' => $startsAt->copy()->addWeeks((int) $plan->duration),
            'MONTHS' => $startsAt->copy()->addMonths((int) $plan->duration),
            default => $startsAt->copy()->addHours((int) $plan->duration),
        };
    }

    private function uniqueCode(int $companyId, int $digits): string
    {
        do {
            $max = (10 ** $digits) - 1;
            $code = str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
        } while (Voucher::query()->where('company_id', $companyId)->where('code', $code)->exists());

        return $code;
    }

    private function resolveRouter(Company $company, int $routerId): NetworkDevice
    {
        $router = NetworkDevice::query()
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->whereKey($routerId)
            ->first();

        if ($router) {
            return $router;
        }

        $deleted = NetworkDevice::onlyTrashed()
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->whereKey($routerId)
            ->exists();

        throw ValidationException::withMessages([
            'router_id' => [
                $deleted
                    ? 'This router has been deleted. Create a new router or restore it before generating vouchers.'
                    : 'Router not found for this company.',
            ],
        ]);
    }

    private function resolvePackage(Company $company, int $packageId): InternetPlan
    {
        $plan = InternetPlan::query()
            ->where('company_id', $company->id)
            ->whereKey($packageId)
            ->first();

        if ($plan) {
            return $plan;
        }

        $deleted = InternetPlan::onlyTrashed()
            ->where('company_id', $company->id)
            ->whereKey($packageId)
            ->exists();

        throw ValidationException::withMessages([
            'package_id' => [
                $deleted
                    ? 'This package has been deleted. Choose an active package.'
                    : 'Package not found for this company.',
            ],
        ]);
    }
}
