<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\PlatformPayment;
use App\Models\RevenueRecord;
use App\Support\PlatformPricing;
use Illuminate\Support\Carbon;

/**
 * Platform-side billing: what WaiFai collects from client companies and the
 * state of their subscriptions. This is deliberately separate from the
 * company dashboard, which reports a client's own hotspot revenue.
 */
class PlatformBillingService
{
    public const DUE_SOON_DAYS = 7;

    public function __construct(private SubscriptionService $subscriptions) {}

    public function currency(): string
    {
        return PlatformPricing::currency();
    }

    public function monthlyFee(): float
    {
        return PlatformPricing::subscriptionMonthly();
    }

    /**
     * Platform revenue and subscription-health overview.
     *
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $companies = Company::query()->get([
            'id',
            'name',
            'status',
            'subscription_status',
            'subscription_period_ends_at',
        ]);

        $counts = [
            'total' => 0,
            'active' => 0,
            'past_due' => 0,
            'expired' => 0,
            'suspended' => 0,
            'cancelled' => 0,
        ];

        $dueSoon = 0;
        $overdue = 0;

        foreach ($companies as $company) {
            $status = $this->subscriptions->effectiveStatus($company);
            $counts['total']++;
            $counts[array_key_exists($status, $counts) ? $status : 'active']++;

            $remaining = $this->subscriptions->daysRemaining($company);

            if (
                in_array($status, ['active', 'past_due'], true)
                && $remaining !== null
                && $remaining >= 0
                && $remaining <= self::DUE_SOON_DAYS
            ) {
                $dueSoon++;
            }

            if ($status === 'expired') {
                $overdue++;
            }
        }

        $fee = $this->monthlyFee();
        $revenue = $this->revenueBetween($from, $to);

        $days = (int) $from->diffInDays($to) + 1;
        $previousFrom = $from->copy()->subDays($days);
        $previousTo = $from->copy()->subSecond();
        $previous = $this->revenueBetween($previousFrom, $previousTo);

        return [
            'currency' => $this->currency(),
            'monthly_fee' => $fee,
            'mrr' => round($fee * $counts['active'], 2),
            'clients' => $counts,
            'due_soon' => [
                'days' => self::DUE_SOON_DAYS,
                'count' => $dueSoon,
                'amount' => round($fee * $dueSoon, 2),
            ],
            'overdue' => [
                'count' => $overdue,
                'amount' => round($fee * $overdue, 2),
            ],
            'revenue' => $revenue,
            'previous_revenue' => [
                'total' => $previous['total'],
                'count' => $previous['count'],
            ],
            'series' => $this->dailySeries($from, $to),
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'timezone' => (string) config('platform.reporting_timezone', 'Africa/Dar_es_Salaam'),
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Management-focused dashboard payload: platform revenue and subscription
     * health only (no client-level top plans / sessions).
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $timezone = (string) config('platform.reporting_timezone', 'Africa/Dar_es_Salaam');
        $now = Carbon::now($timezone);
        $monthStart = $now->copy()->startOfMonth();

        $month = $this->summary($monthStart, $now);
        $today = $this->revenueBetween($now->copy()->startOfDay(), $now);
        $previousMonth = $this->revenueBetween(
            $monthStart->copy()->subMonth(),
            $monthStart->copy()->subSecond(),
        );
        $series = $this->dailySeries($now->copy()->subDays(13)->startOfDay(), $now);

        $newClients = Company::query()
            ->where('created_at', '>=', $monthStart->copy()->utc())
            ->where('created_at', '<=', $now->copy()->utc())
            ->count();

        $upcoming = Company::query()
            ->get()
            ->map(fn (Company $company) => $this->clientRow($company))
            ->filter(fn (array $row) => in_array($row['subscription_status'], ['active', 'past_due', 'expired'], true)
                && $row['days_remaining'] !== null
                && $row['days_remaining'] <= self::DUE_SOON_DAYS * 2)
            ->sortBy('days_remaining')
            ->take(5)
            ->values()
            ->all();

        $collected = (float) $month['revenue']['total'];

        return [
            'currency' => $month['currency'],
            'monthly_fee' => $month['monthly_fee'],
            'mrr' => $month['mrr'],
            'collected_today' => $today['total'],
            'collected_this_month' => $collected,
            'collected_previous_month' => $previousMonth['total'],
            'uncollected_this_month' => max(0, round((float) $month['mrr'] - $collected, 2)),
            'by_purpose' => $month['revenue']['by_purpose'],
            'clients' => [...$month['clients'], 'new_this_month' => $newClients],
            'due_soon' => $month['due_soon'],
            'overdue' => $month['overdue'],
            'upcoming_renewals' => $upcoming,
            'series' => $series,
        ];
    }

    /**
     * Client companies enriched with subscription lifecycle + billing.
     *
     * @param  array{search?: string, subscription_status?: string, expiring_within?: int, sort?: string}  $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function clients(array $filters, int $perPage, int $page): array
    {
        $query = Company::query();
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('subdomain', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $companies = $query->orderByDesc('id')->get();
        $aggregates = $this->paymentAggregates($companies->pluck('id')->all());

        $rows = $companies
            ->map(fn (Company $company) => $this->clientRow($company, $aggregates[$company->id] ?? null))
            ->values();

        $status = (string) ($filters['subscription_status'] ?? '');
        if ($status !== '' && $status !== 'all') {
            $rows = $rows->where('subscription_status', $status);
        }

        if (isset($filters['expiring_within']) && (int) $filters['expiring_within'] > 0) {
            $days = (int) $filters['expiring_within'];
            $rows = $rows->filter(fn (array $row) => $row['days_remaining'] !== null
                && $row['days_remaining'] >= 0
                && $row['days_remaining'] <= $days
                && in_array($row['subscription_status'], ['active', 'past_due'], true));
        }

        $sort = (string) ($filters['sort'] ?? 'period_ends');
        $rows = match ($sort) {
            'name' => $rows->sortBy('name'),
            'created' => $rows->sortByDesc('id'),
            default => $rows->sortBy(fn (array $row) => $row['days_remaining'] ?? PHP_INT_MAX),
        };

        $rows = $rows->values();
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        return [
            'items' => $rows->slice(($page - 1) * $perPage, $perPage)->values()->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * One company with subscription, usage and billing history.
     *
     * @return array<string, mixed>
     */
    public function client(Company $company): array
    {
        $aggregates = $this->paymentAggregates([$company->id]);
        $onlineSince = now()->subMinutes(NetworkDevice::ONLINE_WINDOW_MINUTES);
        $monthStart = Carbon::now($company->reportingTimezone())->startOfMonth()->utc();

        return [
            ...$this->clientRow($company, $aggregates[$company->id] ?? null),
            'subscription' => [
                'plan' => 'monthly',
                'amount' => $this->monthlyFee(),
                'currency' => $this->currency(),
                'status' => $this->subscriptions->effectiveStatus($company),
                'is_access_allowed' => $this->subscriptions->isActive($company),
                'activated_at' => $company->activated_at?->toIso8601String(),
                'period_ends_at' => $company->subscription_period_ends_at?->toIso8601String(),
                'days_remaining' => $this->subscriptions->daysRemaining($company),
                'grace_days' => PlatformPricing::subscriptionGraceDays(),
            ],
            'usage' => [
                'routers_total' => NetworkDevice::query()
                    ->where('company_id', $company->id)->where('type', 'router')->count(),
                'routers_online' => NetworkDevice::query()
                    ->where('company_id', $company->id)->where('type', 'router')
                    ->where('last_seen_at', '>', $onlineSince)->count(),
                'active_sessions' => NetworkSession::query()
                    ->where('company_id', $company->id)->where('status', 'active')->count(),
                'customers_total' => Customer::query()
                    ->where('company_id', $company->id)->count(),
                'client_revenue_this_month' => round((float) RevenueRecord::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'recognized')
                    ->where('recognized_at', '>=', $monthStart)
                    ->sum('amount'), 2),
            ],
            'payments' => PlatformPayment::query()
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (PlatformPayment $payment) => $this->paymentRow($payment))
                ->values()
                ->all(),
        ];
    }

    /**
     * Companies with a subscription due within the window (and overdue ones).
     *
     * @return array<string, mixed>
     */
    public function renewals(int $days): array
    {
        $companies = Company::query()->get();

        $items = $companies
            ->map(fn (Company $company) => $this->clientRow($company))
            ->filter(fn (array $row) => in_array($row['subscription_status'], ['active', 'past_due', 'expired'], true)
                && $row['days_remaining'] !== null
                && $row['days_remaining'] <= $days)
            ->sortBy('days_remaining')
            ->values()
            ->all();

        return [
            'days' => $days,
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Platform revenue payments (subscriptions, renewals, installation, hardware).
     *
     * @param  array{company_id?: int, purpose?: string, status?: string, from?: string, to?: string}  $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function payments(array $filters, int $perPage, int $page): array
    {
        $query = PlatformPayment::query()
            ->with('company')
            ->whereIn('purpose', PlatformPayment::REVENUE_PURPOSES);

        if (! empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }
        if (! empty($filters['purpose'])) {
            $query->where('purpose', (string) $filters['purpose']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('paid_at', '>=', (string) $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('paid_at', '<=', (string) $filters['to']);
        }

        $paginated = $query->latest('id')->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginated->items())
                ->map(fn (PlatformPayment $payment) => $this->paymentRow($payment))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<int, array{count: int, total: float, last_paid_at: string|null, last_amount: float|null}>
     */
    private function paymentAggregates(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $rows = PlatformPayment::query()
            ->whereIn('company_id', $companyIds)
            ->where('status', PlatformPayment::STATUS_PAID)
            ->whereIn('purpose', PlatformPayment::REVENUE_PURPOSES)
            ->selectRaw('company_id, COUNT(*) as count, COALESCE(SUM(amount), 0) as total, MAX(id) as last_id')
            ->groupBy('company_id')
            ->get();

        $lastIds = $rows->pluck('last_id')->filter()->all();
        $lastPayments = $lastIds === []
            ? collect()
            : PlatformPayment::query()->whereIn('id', $lastIds)->get()->keyBy('id');

        return $rows->mapWithKeys(function ($row) use ($lastPayments): array {
            $last = $lastPayments->get($row->last_id);

            return [
                (int) $row->company_id => [
                    'count' => (int) $row->count,
                    'total' => round((float) $row->total, 2),
                    'last_paid_at' => $last?->paid_at?->toIso8601String(),
                    'last_amount' => $last ? (float) $last->amount : null,
                ],
            ];
        })->all();
    }

    /**
     * @param  array{count: int, total: float, last_paid_at: string|null, last_amount: float|null}|null  $aggregate
     * @return array<string, mixed>
     */
    private function clientRow(Company $company, ?array $aggregate = null): array
    {
        $status = $this->subscriptions->effectiveStatus($company);
        $remaining = $this->subscriptions->daysRemaining($company);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'subdomain' => $company->subdomain,
            'email' => $company->email,
            'phone' => $company->phone,
            'status' => $company->status,
            'subscription_status' => $status,
            'subscription_period_ends_at' => $company->subscription_period_ends_at?->toIso8601String(),
            'days_remaining' => $remaining,
            'is_active' => $this->subscriptions->isActive($company),
            'expiring_soon' => in_array($status, ['active', 'past_due'], true)
                && $remaining !== null
                && $remaining >= 0
                && $remaining <= self::DUE_SOON_DAYS,
            'mrr' => $this->monthlyFee(),
            'currency' => $this->currency(),
            'payments_count' => $aggregate['count'] ?? 0,
            'total_paid' => round((float) ($aggregate['total'] ?? 0), 2),
            'last_payment_at' => $aggregate['last_paid_at'] ?? null,
            'last_payment_amount' => $aggregate['last_amount'] ?? null,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(PlatformPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'purpose' => $payment->resolvePurpose(),
            'company_id' => $payment->company_id,
            'company_name' => $payment->relationLoaded('company') ? $payment->company?->name : null,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'provider' => $payment->provider_slug,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'initiated_at' => $payment->initiated_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{total: float, count: int, by_purpose: array<string, array{count: int, total: float}>}
     */
    private function revenueBetween(Carbon $from, Carbon $to): array
    {
        [$fromUtc, $toUtc] = $this->utcBounds($from, $to);

        $rows = PlatformPayment::query()
            ->where('status', PlatformPayment::STATUS_PAID)
            ->whereIn('purpose', PlatformPayment::REVENUE_PURPOSES)
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->selectRaw('purpose, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('purpose')
            ->get();

        $byPurpose = [];
        $total = 0.0;
        $count = 0;

        foreach ($rows as $row) {
            $byPurpose[(string) $row->purpose] = [
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ];
            $total += (float) $row->total;
            $count += (int) $row->count;
        }

        return ['total' => round($total, 2), 'count' => $count, 'by_purpose' => $byPurpose];
    }

    /**
     * @return list<array{date: string, total: float, count: int}>
     */
    private function dailySeries(Carbon $from, Carbon $to): array
    {
        [$fromUtc, $toUtc] = $this->utcBounds($from, $to);

        $rows = PlatformPayment::query()
            ->where('status', PlatformPayment::STATUS_PAID)
            ->whereIn('purpose', PlatformPayment::REVENUE_PURPOSES)
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);
            $series[] = [
                'date' => $date,
                'total' => round((float) ($row->total ?? 0), 2),
                'count' => (int) ($row->count ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function utcBounds(Carbon $from, Carbon $to): array
    {
        $timezone = (string) config('platform.reporting_timezone', 'Africa/Dar_es_Salaam');

        return [
            $from->copy()->setTimezone($timezone)->startOfDay()->utc(),
            $to->copy()->setTimezone($timezone)->endOfDay()->utc(),
        ];
    }
}
