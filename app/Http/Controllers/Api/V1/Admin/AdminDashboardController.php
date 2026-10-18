<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function __construct(private PlatformBillingService $billing) {}

    /**
     * Platform-management dashboard: what WaiFai earns and the state of client
     * subscriptions. Client-level data (their plans, sessions, hotpost revenue)
     * is intentionally excluded.
     */
    public function show(Request $request): JsonResponse
    {
        $platform = $this->billing->dashboard();
        $series = $platform['series'];

        return $this->success([
            'currency' => $platform['currency'],
            'generated_at' => Carbon::now()->toIso8601String(),
            'platform' => $platform,
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
