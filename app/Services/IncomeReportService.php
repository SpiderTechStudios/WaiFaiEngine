<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Voucher;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Income for a local date range. Uses the same revenue rules as the dashboard
 * (revenue_records: mobile money at paid time, voucher once at first redemption
 * at package price), so both screens always agree.
 */
class IncomeReportService
{
    private const PAID_OUT_STATUSES = ['completed', 'paid', 'success'];

    private const PENDING_PAYMENT_STATUSES = ['pending', 'processing'];

    private const FAILED_PAYMENT_STATUSES = ['failed', 'expired', 'cancelled'];

    /**
     * @param  array{branch_id?: ?int, router_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    public function build(Company $company, Carbon $from, Carbon $to, bool $compare, array $filters = []): array
    {
        $timezone = $company->reportingTimezone();
        $currency = (string) config('platform.currency', 'TZS');

        $sales = $this->sales($company, $from, $to, $timezone, $filters);
        $payments = $this->paymentAttempts($company, $from, $to, $filters);

        $mobileMoney = $sales->where('source', 'mobile_money');
        $vouchers = $sales->where('source', 'voucher');

        $gross = round((float) $sales->sum('amount'), 2);
        $mobileMoneyTotal = round((float) $mobileMoney->sum('amount'), 2);
        $voucherTotal = round((float) $vouchers->sum('amount'), 2);
        $fees = 0.0;
        $refunds = $this->refunds($company, $from, $to, $timezone);
        $paidCount = $sales->count();

        $wallet = Wallet::query()->where('company_id', $company->id)->where('currency', $currency)->first();

        $report = [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'timezone' => $timezone,
                'currency' => $currency,
            ],
            'generated_at' => Carbon::now($timezone)->toIso8601String(),

            'totals' => [
                'gross' => $gross,
                'fees' => $fees,
                'net' => round($gross - $fees - $refunds, 2),
                'mobile_money' => $mobileMoneyTotal,
                'vouchers' => $voucherTotal,
                'pending' => round((float) $payments->whereIn('status', self::PENDING_PAYMENT_STATUSES)->sum('amount'), 2),
                'refunds' => $refunds,
                'paid_out' => $this->paidOut($company, $from, $to, $timezone),
                'available_balance' => (float) ($wallet?->balance ?? 0),
                'pending_withdrawals' => (float) Withdrawal::query()
                    ->where('company_id', $company->id)
                    ->where('status', Withdrawal::STATUS_PENDING)
                    ->sum('amount'),
            ],

            'counts' => [
                'paid' => $paidCount,
                'mobile_money_paid' => $mobileMoney->count(),
                'vouchers_sold' => $vouchers->count(),
                'pending' => $payments->whereIn('status', self::PENDING_PAYMENT_STATUSES)->count(),
                'failed' => $payments->whereIn('status', self::FAILED_PAYMENT_STATUSES)->count(),
                'initiated' => $payments->count(),
                'avg_ticket' => $paidCount > 0 ? (int) round($gross / $paidCount) : 0,
            ],
        ];

        if ($compare) {
            $days = (int) $from->diffInDays($to) + 1;
            $previousTo = $from->copy()->subDay();
            $previousFrom = $previousTo->copy()->subDays($days - 1);
            $previousSales = $this->sales($company, $previousFrom, $previousTo, $timezone, $filters);
            $previousGross = round((float) $previousSales->sum('amount'), 2);
            $previousRefunds = $this->refunds($company, $previousFrom, $previousTo, $timezone);

            $report['previous'] = [
                'from' => $previousFrom->toDateString(),
                'to' => $previousTo->toDateString(),
                'gross' => $previousGross,
                'net' => round($previousGross - $previousRefunds, 2),
                'mobile_money' => round((float) $previousSales->where('source', 'mobile_money')->sum('amount'), 2),
                'vouchers' => round((float) $previousSales->where('source', 'voucher')->sum('amount'), 2),
                'paid' => $previousSales->count(),
                'daily' => collect($this->daily($previousSales, $previousFrom, $previousTo))
                    ->map(fn (array $day) => ['date' => $day['date'], 'total' => $day['total']])
                    ->values()
                    ->all(),
            ];
        }

        $report['daily'] = $this->daily($sales, $from, $to);
        $report['by_provider'] = $this->byProvider($mobileMoney);
        $report['by_package'] = $this->byPackage($sales);
        $report['by_router'] = $this->byRouter($sales);
        $report['by_hour'] = $this->byHour($sales);
        $report['customers'] = $this->customers($company, $sales, $from, $timezone);
        $report['vouchers'] = [
            'used' => $vouchers->count(),
            'unused' => Voucher::query()
                ->where('company_id', $company->id)
                ->where('status', Voucher::STATUS_ACTIVE)
                ->where('uses_count', 0)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
        ];

        return $report;
    }

    /**
     * One row per local day from $from to $to (zero days included).
     *
     * @param  Collection<int, array<string, mixed>>  $sales
     * @return list<array{date: string, mobile_money: float, vouchers: float, total: float, count: int}>
     */
    public function daily(Collection $sales, Carbon $from, Carbon $to): array
    {
        $byDay = $sales->groupBy('date');
        $rows = [];

        foreach (CarbonPeriod::create($from->copy(), '1 day', $to->copy()) as $day) {
            $date = $day->toDateString();
            $daySales = $byDay->get($date, collect());
            $mobileMoney = round((float) $daySales->where('source', 'mobile_money')->sum('amount'), 2);
            $vouchers = round((float) $daySales->where('source', 'voucher')->sum('amount'), 2);

            $rows[] = [
                'date' => $date,
                'mobile_money' => $mobileMoney,
                'vouchers' => $vouchers,
                'total' => round($mobileMoney + $vouchers, 2),
                'count' => $daySales->count(),
            ];
        }

        return $rows;
    }

    /**
     * Recognized sales in the local range, enriched with package, provider,
     * router, branch and customer.
     *
     * @param  array{branch_id?: ?int, router_id?: ?int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function sales(Company $company, Carbon $from, Carbon $to, string $timezone, array $filters = []): Collection
    {
        [$fromUtc, $toUtc] = $this->utcBounds($from, $to, $timezone);

        $rows = DB::table('revenue_records as rr')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 'rr.payment_transaction_id')
            ->leftJoin('vouchers as v', 'v.id', '=', 'rr.voucher_id')
            ->leftJoin('access_grants as ag', 'ag.id', '=', 'rr.access_grant_id')
            ->where('rr.company_id', $company->id)
            ->where('rr.status', 'recognized')
            ->whereIn('rr.source', ['mobile_money', 'voucher'])
            ->where('rr.recognized_at', '>=', $fromUtc)
            ->where('rr.recognized_at', '<=', $toUtc)
            ->orderBy('rr.recognized_at')
            ->get([
                'rr.id',
                'rr.source',
                'rr.amount',
                'rr.recognized_at',
                'rr.network_station_id',
                'rr.payment_transaction_id',
                'rr.access_grant_id',
                'pt.payment_method',
                'pt.metadata as payment_metadata',
                'pt.internet_plan_id as payment_plan_id',
                'pt.customer_id as payment_customer_id',
                'v.internet_plan_id as voucher_plan_id',
                'v.network_device_id as voucher_router_id',
                'ag.customer_id as grant_customer_id',
                'ag.internet_plan_id as grant_plan_id',
            ]);

        $routers = $this->routerResolver(
            $rows->pluck('payment_transaction_id')->filter()->all(),
            $rows->pluck('access_grant_id')->filter()->all(),
            $rows->pluck('payment_metadata')->all(),
        );

        $sales = $rows->map(function ($row) use ($timezone, $routers): array {
            $local = Carbon::parse($row->recognized_at, 'UTC')->setTimezone($timezone);
            $metadata = $this->decodeMetadata($row->payment_metadata);

            $routerId = $routers['byPayment'][$row->payment_transaction_id] ?? null;
            $routerId ??= $routers['byGrant'][$row->access_grant_id] ?? null;
            $routerId ??= isset($metadata['captive_session']) ? ($routers['byCaptiveToken'][$metadata['captive_session']] ?? null) : null;
            $routerId ??= $row->voucher_router_id;

            return [
                'source' => $row->source,
                'amount' => (float) $row->amount,
                'date' => $local->toDateString(),
                'hour' => (int) $local->format('G'),
                'package_id' => $row->payment_plan_id ?? $row->voucher_plan_id ?? $row->grant_plan_id,
                'provider' => $row->source === 'mobile_money'
                    ? $this->providerKey($row->payment_method, $metadata['network'] ?? null)
                    : null,
                'router_id' => $routerId ? (int) $routerId : null,
                'branch_id' => $row->network_station_id ? (int) $row->network_station_id : null,
                'customer_id' => $row->payment_customer_id ?? $row->grant_customer_id,
            ];
        });

        $sales = $this->attachRouterDetails($sales);

        return $this->applyFilters($sales, $filters)->values();
    }

    /**
     * Mobile money payments created in the range (any status), for
     * pending / failed / initiated counts.
     *
     * @param  array{branch_id?: ?int, router_id?: ?int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function paymentAttempts(Company $company, Carbon $from, Carbon $to, array $filters): Collection
    {
        [$fromUtc, $toUtc] = $this->utcBounds($from, $to, $company->reportingTimezone());

        $rows = DB::table('payment_transactions')
            ->where('company_id', $company->id)
            ->where('payment_method', '!=', 'voucher')
            ->where('created_at', '>=', $fromUtc)
            ->where('created_at', '<=', $toUtc)
            ->get(['id', 'status', 'amount', 'metadata', 'network_station_id']);

        if (empty(array_filter($filters))) {
            return $rows->map(fn ($row) => ['status' => $row->status, 'amount' => (float) $row->amount]);
        }

        $routers = $this->routerResolver($rows->pluck('id')->all(), [], $rows->pluck('metadata')->all());

        $attempts = $rows->map(function ($row) use ($routers): array {
            $metadata = $this->decodeMetadata($row->metadata);
            $routerId = $routers['byPayment'][$row->id] ?? null;
            $routerId ??= isset($metadata['captive_session']) ? ($routers['byCaptiveToken'][$metadata['captive_session']] ?? null) : null;

            return [
                'status' => $row->status,
                'amount' => (float) $row->amount,
                'router_id' => $routerId ? (int) $routerId : null,
                'branch_id' => $row->network_station_id ? (int) $row->network_station_id : null,
            ];
        });

        return $this->applyFilters($this->attachRouterDetails($attempts), $filters)->values();
    }

    /**
     * @param  list<int>  $paymentIds
     * @param  list<int>  $grantIds
     * @param  list<mixed>  $paymentMetadata
     * @return array{byPayment: array<int, int>, byGrant: array<int, int>, byCaptiveToken: array<string, int>}
     */
    private function routerResolver(array $paymentIds, array $grantIds, array $paymentMetadata): array
    {
        $byPayment = [];
        $byGrant = [];
        $byCaptiveToken = [];

        if ($paymentIds !== []) {
            DB::table('network_sessions')
                ->whereIn('payment_transaction_id', array_values(array_unique($paymentIds)))
                ->whereNotNull('network_device_id')
                ->orderBy('id')
                ->get(['payment_transaction_id', 'network_device_id'])
                ->each(function ($row) use (&$byPayment): void {
                    $byPayment[$row->payment_transaction_id] ??= (int) $row->network_device_id;
                });
        }

        if ($grantIds !== []) {
            DB::table('network_sessions')
                ->whereIn('access_grant_id', array_values(array_unique($grantIds)))
                ->whereNotNull('network_device_id')
                ->orderBy('id')
                ->get(['access_grant_id', 'network_device_id'])
                ->each(function ($row) use (&$byGrant): void {
                    $byGrant[$row->access_grant_id] ??= (int) $row->network_device_id;
                });
        }

        $tokens = collect($paymentMetadata)
            ->map(fn ($metadata) => $this->decodeMetadata($metadata)['captive_session'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens !== []) {
            $byCaptiveToken = DB::table('captive_sessions')
                ->whereIn('token', $tokens)
                ->pluck('network_device_id', 'token')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return compact('byPayment', 'byGrant', 'byCaptiveToken');
    }

    /**
     * Fill router name and branch (the router's station wins over the record's).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function attachRouterDetails(Collection $rows): Collection
    {
        $routerIds = $rows->pluck('router_id')->filter()->unique()->values()->all();

        $routers = $routerIds === []
            ? collect()
            : DB::table('network_devices')->whereIn('id', $routerIds)->get(['id', 'name', 'network_station_id'])->keyBy('id');

        $branchIds = $routers->pluck('network_station_id')
            ->merge($rows->pluck('branch_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $branches = $branchIds === []
            ? collect()
            : DB::table('network_stations')->whereIn('id', $branchIds)->pluck('name', 'id');

        return $rows->map(function (array $row) use ($routers, $branches): array {
            $router = $row['router_id'] ? $routers->get($row['router_id']) : null;
            $branchId = $router?->network_station_id ? (int) $router->network_station_id : $row['branch_id'];

            return [
                ...$row,
                'router_name' => $router?->name,
                'branch_id' => $branchId,
                'branch_name' => $branchId ? $branches->get($branchId) : null,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{branch_id?: ?int, router_id?: ?int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        if (! empty($filters['router_id'])) {
            $rows = $rows->where('router_id', (int) $filters['router_id']);
        }

        if (! empty($filters['branch_id'])) {
            $rows = $rows->where('branch_id', (int) $filters['branch_id']);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $mobileMoney
     * @return list<array{provider: string, amount: float, count: int}>
     */
    private function byProvider(Collection $mobileMoney): array
    {
        return $mobileMoney
            ->groupBy('provider')
            ->map(fn (Collection $rows, string $provider) => [
                'provider' => $provider,
                'amount' => round((float) $rows->sum('amount'), 2),
                'count' => $rows->count(),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sales
     * @return list<array<string, mixed>>
     */
    private function byPackage(Collection $sales): array
    {
        $withPackage = $sales->filter(fn (array $sale) => $sale['package_id'] !== null);

        $names = $withPackage->isEmpty()
            ? collect()
            : DB::table('internet_plans')->whereIn('id', $withPackage->pluck('package_id')->unique()->all())->pluck('name', 'id');

        return $withPackage
            ->groupBy('package_id')
            ->map(fn (Collection $rows, $packageId) => [
                'package_id' => (int) $packageId,
                'name' => $names->get($packageId) ?? 'Package #'.$packageId,
                'amount' => round((float) $rows->sum('amount'), 2),
                'count' => $rows->count(),
                'mobile_money' => round((float) $rows->where('source', 'mobile_money')->sum('amount'), 2),
                'vouchers' => round((float) $rows->where('source', 'voucher')->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sales
     * @return list<array<string, mixed>>
     */
    private function byRouter(Collection $sales): array
    {
        return $sales
            ->filter(fn (array $sale) => $sale['router_id'] !== null)
            ->groupBy('router_id')
            ->map(function (Collection $rows, $routerId): array {
                $first = $rows->first();

                return [
                    'router_id' => (int) $routerId,
                    'name' => $first['router_name'] ?? 'Router #'.$routerId,
                    'branch_id' => $first['branch_id'],
                    'branch' => $first['branch_name'],
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'count' => $rows->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sales
     * @return list<array{hour: int, amount: float, count: int}>
     */
    private function byHour(Collection $sales): array
    {
        $byHour = $sales->groupBy('hour');

        return collect(range(0, 23))
            ->map(fn (int $hour) => [
                'hour' => $hour,
                'amount' => round((float) $byHour->get($hour, collect())->sum('amount'), 2),
                'count' => $byHour->get($hour, collect())->count(),
            ])
            ->all();
    }

    /**
     * new = first ever paid purchase is in range; returning = bought in range
     * and also before the range.
     *
     * @param  Collection<int, array<string, mixed>>  $sales
     * @return array{new: int, returning: int}
     */
    private function customers(Company $company, Collection $sales, Carbon $from, string $timezone): array
    {
        $customerIds = $sales->pluck('customer_id')->filter()->unique()->values()->all();

        if ($customerIds === []) {
            return ['new' => 0, 'returning' => 0];
        }

        [$fromUtc] = $this->utcBounds($from, $from, $timezone);
        $customerExpression = DB::raw('COALESCE(pt.customer_id, ag.customer_id)');

        $returning = DB::table('revenue_records as rr')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 'rr.payment_transaction_id')
            ->leftJoin('access_grants as ag', 'ag.id', '=', 'rr.access_grant_id')
            ->where('rr.company_id', $company->id)
            ->where('rr.status', 'recognized')
            ->where('rr.recognized_at', '<', $fromUtc)
            ->whereIn($customerExpression, $customerIds)
            ->distinct()
            ->count($customerExpression);

        return [
            'new' => count($customerIds) - $returning,
            'returning' => $returning,
        ];
    }

    /**
     * Processed refunds in the range. 0 until refunds are used.
     */
    private function refunds(Company $company, Carbon $from, Carbon $to, string $timezone): float
    {
        [$fromUtc, $toUtc] = $this->utcBounds($from, $to, $timezone);

        return round((float) DB::table('refunds')
            ->where('company_id', $company->id)
            ->whereIn('status', ['processed', 'completed', 'refunded'])
            ->where('processed_at', '>=', $fromUtc)
            ->where('processed_at', '<=', $toUtc)
            ->sum('amount'), 2);
    }

    private function paidOut(Company $company, Carbon $from, Carbon $to, string $timezone): float
    {
        [$fromUtc, $toUtc] = $this->utcBounds($from, $to, $timezone);

        return round((float) Withdrawal::query()
            ->where('company_id', $company->id)
            ->whereIn('status', self::PAID_OUT_STATUSES)
            ->whereRaw('COALESCE(processed_at, updated_at) >= ?', [$fromUtc])
            ->whereRaw('COALESCE(processed_at, updated_at) <= ?', [$toUtc])
            ->sum('amount'), 2);
    }

    /**
     * Mobile network key, independent of the aggregator (palmpesa, ...).
     */
    private function providerKey(?string $paymentMethod, ?string $network): string
    {
        $value = strtolower((string) ($network ?: $paymentMethod));
        $value = preg_replace('/[^a-z]/', '', $value) ?? '';

        return match (true) {
            str_contains($value, 'mpesa') || str_contains($value, 'vodacom') => 'mpesa',
            str_contains($value, 'mixx') || str_contains($value, 'yas') => 'mixx',
            str_contains($value, 'tigo') => 'tigo',
            str_contains($value, 'airtel') => 'airtel',
            str_contains($value, 'halo') => 'halopesa',
            str_contains($value, 'ttcl') => 'ttcl',
            default => 'other',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function utcBounds(Carbon $from, Carbon $to, string $timezone): array
    {
        return [
            $from->copy()->setTimezone($timezone)->startOfDay()->utc()->format('Y-m-d H:i:s'),
            $to->copy()->setTimezone($timezone)->endOfDay()->utc()->format('Y-m-d H:i:s'),
        ];
    }
}
