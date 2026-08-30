<?php

namespace App\Services;

use App\Models\Company;
use App\Support\PlatformPricing;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Sync expired/past_due based on period end + grace, then return whether access is allowed.
     */
    public function syncAndIsActive(Company $company): bool
    {
        $this->syncStatus($company);
        $company->refresh();

        return $this->isActive($company);
    }

    public function syncStatus(Company $company): void
    {
        if (! $company->subscription_period_ends_at) {
            return;
        }

        if (in_array($company->subscription_status, ['cancelled', 'suspended'], true)) {
            return;
        }

        $graceEnds = $company->subscription_period_ends_at
            ->copy()
            ->addDays(PlatformPricing::subscriptionGraceDays());

        if ($company->subscription_period_ends_at->isFuture()) {
            if ($company->subscription_status !== 'active') {
                $company->forceFill(['subscription_status' => 'active'])->save();
            }

            return;
        }

        if ($graceEnds->isFuture()) {
            if ($company->subscription_status !== 'past_due') {
                $company->forceFill(['subscription_status' => 'past_due'])->save();
            }

            return;
        }

        if ($company->subscription_status !== 'expired') {
            $company->forceFill(['subscription_status' => 'expired'])->save();
        }
    }

    public function isActive(Company $company): bool
    {
        if (in_array($company->subscription_status, ['cancelled', 'suspended', 'expired'], true)) {
            return false;
        }

        if (! $company->subscription_period_ends_at) {
            return $company->subscription_status === 'active';
        }

        $graceEnds = $company->subscription_period_ends_at
            ->copy()
            ->addDays(PlatformPricing::subscriptionGraceDays());

        return $graceEnds->isFuture()
            && in_array($company->subscription_status, ['active', 'past_due'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Company $company): array
    {
        $this->syncStatus($company);
        $company->refresh();

        return [
            'plan' => 'monthly',
            'currency' => PlatformPricing::currency(),
            'amount' => PlatformPricing::subscriptionMonthly(),
            'status' => $company->subscription_status,
            'is_access_allowed' => $this->isActive($company),
            'activated_at' => $company->activated_at,
            'period_ends_at' => $company->subscription_period_ends_at,
            'next_billing_at' => $company->subscription_period_ends_at,
            'grace_days' => PlatformPricing::subscriptionGraceDays(),
        ];
    }

    public function extendPeriod(Company $company, ?Carbon $from = null): Company
    {
        $from ??= ($company->subscription_period_ends_at && $company->subscription_period_ends_at->isFuture()
            ? $company->subscription_period_ends_at
            : now());

        $company->forceFill([
            'subscription_status' => 'active',
            'subscription_period_ends_at' => $from->copy()->addMonth(),
            'activated_at' => $company->activated_at ?? now(),
        ])->save();

        return $company->fresh();
    }
}
