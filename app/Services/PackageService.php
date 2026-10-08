<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageService
{
    public const PERIODS = ['today', '7d', '30d', '90d', 'all'];

    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Company $company, array $data, User $actor): InternetPlan
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $plan = InternetPlan::query()->create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($company->id, $data['name']),
                'badge' => $data['badge'] ?? null,
                'description' => $data['description'] ?? null,
                'duration' => $data['duration_unit'] === 'UNLIMITED_DATA' ? null : $data['duration'],
                'duration_unit' => $data['duration_unit'],
                'price' => $data['price'],
                'speed_download_mbps' => $data['speed_download_mbps'] ?? null,
                'speed_upload_mbps' => $data['speed_upload_mbps'] ?? null,
                'data_cap_mb' => $data['data_cap_mb'] ?? null,
                'devices_allowed' => $data['devices_allowed'] ?? null,
                'status' => $data['status'] ?? InternetPlan::STATUS_ACTIVE,
                'sort_order' => $data['sort_order'] ?? $this->nextSortOrder($company->id),
                'visible_on_portal' => $data['visible_on_portal'] ?? true,
            ]);

            $this->auditLogger->log('package_created', $actor, $company->id, InternetPlan::class, $plan->id);

            return $plan;
        });
    }

    public function update(InternetPlan $plan, array $data, User $actor): InternetPlan
    {
        if (($data['duration_unit'] ?? $plan->duration_unit) === 'UNLIMITED_DATA') {
            $data['duration'] = null;
        }

        $plan->fill($data)->save();

        $this->auditLogger->log('package_updated', $actor, $plan->company_id, InternetPlan::class, $plan->id);

        return $plan;
    }

    public function delete(InternetPlan $plan, User $actor): void
    {
        $plan->forceFill(['status' => InternetPlan::STATUS_INACTIVE])->save();
        $plan->delete();
        $this->auditLogger->log('package_deleted', $actor, $plan->company_id, InternetPlan::class, $plan->id);
    }

    /**
     * Copy a package. The copy starts inactive and hidden from the portal so the
     * operator can review it before publishing.
     */
    public function duplicate(InternetPlan $plan, User $actor): InternetPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $name = $this->uniqueCopyName($plan->company_id, $plan->name);

            $copy = $plan->replicate(['slug']);
            $copy->name = $name;
            $copy->slug = $this->uniqueSlug($plan->company_id, $name);
            $copy->status = InternetPlan::STATUS_INACTIVE;
            $copy->visible_on_portal = false;
            $copy->sort_order = $this->nextSortOrder($plan->company_id);
            $copy->save();

            $this->auditLogger->log('package_duplicated', $actor, $plan->company_id, InternetPlan::class, $copy->id);

            return $copy->fresh();
        });
    }

    /**
     * Apply the given portal order. Listed packages get 1..n; everything else
     * keeps its relative order after them.
     *
     * @param  list<int>  $ids
     * @return Collection<int, InternetPlan>
     */
    public function reorder(Company $company, array $ids, User $actor): Collection
    {
        $ordered = array_values(array_unique(array_map('intval', $ids)));

        return DB::transaction(function () use ($company, $ordered, $actor) {
            $plans = InternetPlan::query()
                ->where('company_id', $company->id)
                ->ordered()
                ->get();

            $byId = $plans->keyBy('id');
            $position = 1;

            foreach ($ordered as $id) {
                $plan = $byId->get($id);
                if (! $plan) {
                    continue;
                }

                $plan->forceFill(['sort_order' => $position++])->save();
                $byId->forget($id);
            }

            foreach ($byId->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $plan) {
                $plan->forceFill(['sort_order' => $position++])->save();
            }

            $this->auditLogger->log('package_reordered', $actor, $company->id, InternetPlan::class, null);

            return InternetPlan::query()
                ->where('company_id', $company->id)
                ->ordered()
                ->get();
        });
    }

    /**
     * How many financial records reference this package. Deleting it would break
     * their history, so the API blocks the delete with a 409 instead.
     *
     * @return array{payments: int, vouchers: int, access_grants: int, sessions: int}
     */
    public function usageCounts(InternetPlan $plan): array
    {
        return [
            'payments' => PaymentTransaction::query()->where('internet_plan_id', $plan->id)->count(),
            'vouchers' => Voucher::query()->where('internet_plan_id', $plan->id)->count(),
            'access_grants' => AccessGrant::query()->where('internet_plan_id', $plan->id)->count(),
            'sessions' => NetworkSession::query()->where('internet_plan_id', $plan->id)->count(),
        ];
    }

    /**
     * Recognised sales per package. Revenue attribution matches the income report:
     * mobile money at payment time, voucher at first redemption.
     *
     * @return array<int, array{sold: int, revenue: float, last_sold_at: string|null}>
     */
    public function salesByPackage(Company $company, ?Carbon $fromUtc = null, ?Carbon $toUtc = null): array
    {
        $rows = DB::table('revenue_records as rr')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 'rr.payment_transaction_id')
            ->leftJoin('vouchers as v', 'v.id', '=', 'rr.voucher_id')
            ->leftJoin('access_grants as ag', 'ag.id', '=', 'rr.access_grant_id')
            ->where('rr.company_id', $company->id)
            ->where('rr.status', 'recognized')
            ->whereIn('rr.source', ['mobile_money', 'voucher'])
            ->when($fromUtc, fn ($query) => $query->where('rr.recognized_at', '>=', $fromUtc))
            ->when($toUtc, fn ($query) => $query->where('rr.recognized_at', '<=', $toUtc))
            ->selectRaw('COALESCE(pt.internet_plan_id, v.internet_plan_id, ag.internet_plan_id) as plan_id')
            ->selectRaw('COUNT(*) as sold')
            ->selectRaw('SUM(rr.amount) as revenue')
            ->selectRaw('MAX(rr.recognized_at) as last_sold_at')
            ->groupByRaw('COALESCE(pt.internet_plan_id, v.internet_plan_id, ag.internet_plan_id)')
            ->get();

        $sales = [];

        foreach ($rows as $row) {
            if ($row->plan_id === null) {
                continue;
            }

            $sales[(int) $row->plan_id] = [
                'sold' => (int) $row->sold,
                'revenue' => round((float) $row->revenue, 2),
                'last_sold_at' => $row->last_sold_at
                    ? Carbon::parse($row->last_sold_at, 'UTC')->toIso8601String()
                    : null,
            ];
        }

        return $sales;
    }

    /**
     * Header totals for the packages page.
     *
     * @return array<string, mixed>
     */
    public function summary(Company $company, string $period): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : '30d';
        $timezone = $company->reportingTimezone();
        $now = Carbon::now($timezone);

        [$from, $to] = $this->periodBounds($period, $now);
        $fromUtc = $from?->copy()->utc();
        $toUtc = $to?->copy()->utc();

        $plans = InternetPlan::query()
            ->where('company_id', $company->id)
            ->get(['id', 'name', 'status', 'price', 'sort_order']);

        $activePrices = $plans
            ->where('status', InternetPlan::STATUS_ACTIVE)
            ->pluck('price')
            ->map(fn ($price) => (float) $price)
            ->values();

        $sales = $this->salesByPackage($company, $fromUtc, $toUtc);
        $revenue = round(array_sum(array_column($sales, 'revenue')), 2);

        $previousRevenue = 0.0;
        if ($from && $to) {
            [$previousFrom, $previousTo] = $this->previousBounds($from, $to);
            $previousSales = $this->salesByPackage(
                $company,
                $previousFrom->copy()->utc(),
                $previousTo->copy()->utc(),
            );
            $previousRevenue = round(array_sum(array_column($previousSales, 'revenue')), 2);
        }

        $bestSeller = null;
        if ($sales !== []) {
            $planNames = $plans->pluck('name', 'id');
            $ranked = collect($sales)
                ->map(fn (array $row, int $planId) => [
                    'id' => $planId,
                    'name' => (string) ($planNames[$planId] ?? 'Package #'.$planId),
                    'sold' => $row['sold'],
                    'revenue' => $row['revenue'],
                ])
                ->sortByDesc(fn (array $row) => [$row['sold'], $row['revenue']])
                ->first();

            $bestSeller = $ranked;
        }

        return [
            'period' => $period,
            'range' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'timezone' => $timezone,
            ],
            'total' => $plans->count(),
            'active' => $plans->where('status', InternetPlan::STATUS_ACTIVE)->count(),
            'inactive' => $plans->where('status', InternetPlan::STATUS_INACTIVE)->count(),
            'draft' => $plans->where('status', InternetPlan::STATUS_DRAFT)->count(),
            'average_price' => $activePrices->isEmpty() ? null : (int) round($activePrices->avg()),
            'lowest_price' => $activePrices->isEmpty() ? null : (int) $activePrices->min(),
            'highest_price' => $activePrices->isEmpty() ? null : (int) $activePrices->max(),
            'best_seller' => $bestSeller,
            'revenue' => $revenue,
            'previous_revenue' => $previousRevenue,
            'currency' => (string) config('platform.currency', 'TZS'),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function periodBounds(string $period, Carbon $now): array
    {
        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()],
            default => [null, null],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousBounds(Carbon $from, Carbon $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;

        return [
            $from->copy()->subDays($days),
            $from->copy()->subSecond(),
        ];
    }

    private function nextSortOrder(int $companyId): int
    {
        return (int) InternetPlan::withTrashed()
            ->where('company_id', $companyId)
            ->max('sort_order') + 1;
    }

    private function uniqueCopyName(int $companyId, string $name): string
    {
        $base = $name.' (copy)';
        $candidate = $base;
        $i = 2;

        while (InternetPlan::withTrashed()
            ->where('company_id', $companyId)
            ->where('name', $candidate)
            ->exists()) {
            $candidate = $base.' '.$i++;
        }

        return $candidate;
    }

    private function uniqueSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'package';
        $slug = $base;
        $i = 1;

        // Soft-deleted rows still occupy the unique index, so include them.
        while (InternetPlan::withTrashed()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
