<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use Illuminate\Support\Carbon;

/**
 * Live router metrics (clients, recognised revenue, online counts).
 *
 * Revenue is attributed to a router exactly like the income report does
 * (session payment/grant/captive-token resolution), so the routers page and
 * the income page always agree.
 */
class RouterMetricsService
{
    public function __construct(private IncomeReportService $incomeReportService) {}

    /**
     * Recognised revenue per router for a local date range.
     *
     * @return array<int, array{amount: float, count: int}>
     */
    public function revenueByRouter(Company $company, Carbon $from, Carbon $to): array
    {
        $sales = $this->incomeReportService->sales(
            $company,
            $from->copy(),
            $to->copy(),
            $company->reportingTimezone(),
        );

        $totals = [];

        foreach ($sales as $sale) {
            $routerId = $sale['router_id'] ?? null;
            if ($routerId === null) {
                continue;
            }

            $totals[$routerId] ??= ['amount' => 0.0, 'count' => 0];
            $totals[$routerId]['amount'] += (float) $sale['amount'];
            $totals[$routerId]['count']++;
        }

        return array_map(
            fn (array $row) => ['amount' => round($row['amount'], 2), 'count' => $row['count']],
            $totals,
        );
    }

    /**
     * Live clients per router: active sessions whose access grant is still usable.
     *
     * @param  list<int>|null  $routerIds
     * @return array<int, int>
     */
    public function clientsNow(Company $company, ?array $routerIds = null): array
    {
        return NetworkSession::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereNotNull('network_device_id')
            ->when($routerIds !== null, fn ($query) => $query->whereIn('network_device_id', $routerIds))
            ->whereHas('accessGrant', function ($query) {
                $query->where('status', 'active')
                    ->where(fn ($inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            })
            ->groupBy('network_device_id')
            ->selectRaw('network_device_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'network_device_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Header totals for the routers page. Counts cover every router in the
     * company, regardless of any list filter or page.
     *
     * @return array<string, mixed>
     */
    public function summary(Company $company): array
    {
        $timezone = $company->reportingTimezone();
        $now = Carbon::now($timezone);

        $routers = NetworkDevice::query()
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->get(['id', 'network_station_id', 'last_seen_at']);

        $total = $routers->count();
        $online = $routers->filter(fn (NetworkDevice $router) => $router->isOnline())->count();
        $clients = $this->clientsNow($company);
        $revenue = $this->revenueByRouter($company, $now->copy()->startOfDay(), $now);

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online,
            'clients_now' => array_sum($clients),
            'revenue_today' => round(array_sum(array_column($revenue, 'amount')), 2),
            'currency' => (string) config('platform.currency', 'TZS'),
            'branches' => $routers->pluck('network_station_id')->filter()->unique()->count(),
            'generated_at' => $now->toIso8601String(),
        ];
    }
}
