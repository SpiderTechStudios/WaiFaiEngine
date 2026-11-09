<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutAccountService
{
    public function __construct(
        private WithdrawalFeeService $feeService,
        private PaymentMethodResolver $paymentMethods,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, PayoutAccount>
     */
    public function list(Company $company)
    {
        return PayoutAccount::query()
            ->with('paymentMethod')
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): PayoutAccount
    {
        $phone = $this->feeService->normalizePhone((string) $data['phone']);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['A valid payout mobile number is required.'],
            ]);
        }

        return DB::transaction(function () use ($company, $data, $phone, $actor) {
            $exists = PayoutAccount::query()
                ->where('company_id', $company->id)
                ->where('phone', $phone)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => ['This payout number is already saved.'],
                ]);
            }

            $isFirst = ! PayoutAccount::query()->where('company_id', $company->id)->exists();
            $isDefault = $isFirst || (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                PayoutAccount::query()->where('company_id', $company->id)->update(['is_default' => false]);
            }

            $account = PayoutAccount::query()->create([
                'company_id' => $company->id,
                'name' => trim((string) $data['name']),
                'phone' => $phone,
                'network' => $this->feeService->detectNetwork($phone),
                'payment_method_id' => $this->paymentMethods->detect($phone)?->id,
                'is_default' => $isDefault,
            ]);

            $this->auditLogger->log('payout_account_created', $actor, $company->id, PayoutAccount::class, $account->id);

            return $account;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, PayoutAccount $account, array $data, User $actor): PayoutAccount
    {
        if ($account->company_id !== $company->id) {
            abort(404);
        }

        return DB::transaction(function () use ($company, $account, $data, $actor) {
            $attributes = [];

            if (array_key_exists('name', $data)) {
                $attributes['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('phone', $data)) {
                $phone = $this->feeService->normalizePhone((string) $data['phone']);

                $duplicate = PayoutAccount::query()
                    ->where('company_id', $company->id)
                    ->where('phone', $phone)
                    ->whereKeyNot($account->id)
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'phone' => ['This payout number is already saved.'],
                    ]);
                }

                $attributes['phone'] = $phone;
                $attributes['network'] = $this->feeService->detectNetwork($phone);
                $attributes['payment_method_id'] = $this->paymentMethods->detect($phone)?->id;
            }

            $account->fill($attributes)->save();

            if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
                PayoutAccount::query()->where('company_id', $company->id)->whereKeyNot($account->id)->update(['is_default' => false]);
                $account->forceFill(['is_default' => true])->save();
            }

            $this->auditLogger->log('payout_account_updated', $actor, $company->id, PayoutAccount::class, $account->id);

            return $account->fresh();
        });
    }

    public function delete(Company $company, PayoutAccount $account, User $actor): void
    {
        if ($account->company_id !== $company->id) {
            abort(404);
        }

        DB::transaction(function () use ($company, $account, $actor) {
            $wasDefault = $account->is_default;
            $account->delete();

            if ($wasDefault) {
                $next = PayoutAccount::query()->where('company_id', $company->id)->latest('id')->first();
                $next?->forceFill(['is_default' => true])->save();
            }

            $this->auditLogger->log('payout_account_deleted', $actor, $company->id, PayoutAccount::class, $account->id);
        });
    }

    public function setDefault(Company $company, PayoutAccount $account, User $actor): PayoutAccount
    {
        if ($account->company_id !== $company->id) {
            abort(404);
        }

        return DB::transaction(function () use ($company, $account, $actor) {
            PayoutAccount::query()->where('company_id', $company->id)->update(['is_default' => false]);
            $account->forceFill(['is_default' => true])->save();

            $this->auditLogger->log('payout_account_default_set', $actor, $company->id, PayoutAccount::class, $account->id);

            return $account->fresh();
        });
    }
}
