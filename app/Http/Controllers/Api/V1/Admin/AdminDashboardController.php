<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\RevenueRecord;
use App\Services\PlatformBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function __construct(private PlatformBillingService $billing) {}

    /**
     * Platform-management dashboard: what WaiFai earns and the state of client
     * subscriptions, plus the aggregate of clients' own payments through the
     * platform. Per-client structures (their plans/sessions) are excluded.
     */
    public function show(Request $request): JsonResponse
    {
        $platform = $this->billing->dashboard();
        $series = $platform['series'];

        $today = Carbon::today();

        $clientsRevenueToday = round((float) RevenueRecord::query()
            ->where('status', 'recognized')
            ->whereDate('recognized_at', $today)
            ->sum('amount'), 2);

        $clientsRevenueTotal = round((float) RevenueRecord::query()
            ->where('status', 'recognized')
            ->sum('amount'), 2);

        $clientsPaymentsToday = PaymentTransaction::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->count();

        $clientsPaymentsTotal = PaymentTransaction::query()
            ->where('status', 'paid')
            ->count();

        return $this->success([
            'currency' => $platform['currency'],
            'generated_at' => Carbon::now()->toIso8601String(),
            'platform' => $platform,

            // Aggregate of clients' own sales through the platform (all customers).
            'clients_activity' => [
                'revenue_today' => $clientsRevenueToday,
                'revenue_total' => $clientsRevenueTotal,
                'payments_today' => $clientsPaymentsToday,
                'payments_total' => $clientsPaymentsTotal,
            ],

            'revenue_statistics' => [
                'period_days' => 14,
                'total' => round(array_sum(array_column($series, 'total')), 2),
                'last_14_days' => array_map(
                    fn (array $row): array => ['date' => $row['date'], 'total' => $row['total']],
                    $series,
                ),
            ],
        ], 'Admin dashboard retrieved');
    }
}
