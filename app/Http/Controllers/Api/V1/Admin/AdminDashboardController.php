<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\RevenueRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $from = $today->copy()->subDays(13)->startOfDay();
        $onlineSince = now()->subMinutes(10);
        $currency = (string) config('platform.currency', 'TZS');

        $todayRevenue = (float) RevenueRecord::query()
            ->whereDate('recognized_at', $today)
            ->sum('amount');

        $totalRevenue = (float) RevenueRecord::query()->sum('amount');

        $todayPayments = PaymentTransaction::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->count();

        $activeSessions = NetworkSession::query()
            ->where('status', 'active')
            ->count();

        $routersOnline = NetworkDevice::query()
            ->where('type', 'router')
            ->where('last_seen_at', '>', $onlineSince)
            ->count();

        $routersTotal = NetworkDevice::query()
            ->where('type', 'router')
            ->count();

        $recentSessions = NetworkSession::query()
            ->with(['customer', 'company', 'internetPlan'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (NetworkSession $session) => [
                'id' => $session->id,
                'mac_address' => $session->mac_address,
                'status' => $session->status,
                'company_id' => $session->company_id,
                'company_name' => $session->company?->name,
                'plan_name' => $session->internetPlan?->name,
                'customer_name' => $session->customer?->name,
                'description' => ($session->customer?->name ?? 'Guest')
                    .' · '.($session->internetPlan?->name ?? 'No plan')
                    .' · '.($session->company?->name ?? 'Unknown client'),
                'started_at' => $session->started_at,
            ])
            ->values();

        $topPlanRows = NetworkSession::query()
            ->select('internet_plan_id', DB::raw('COUNT(*) as usage_count'))
            ->whereNotNull('internet_plan_id')
            ->where('created_at', '>=', $from)
            ->groupBy('internet_plan_id')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get();

        $plans = InternetPlan::query()
            ->with('company')
            ->whereIn('id', $topPlanRows->pluck('internet_plan_id'))
            ->get()
            ->keyBy('id');

        $topPlans = $topPlanRows->map(function ($row) use ($plans) {
            $plan = $plans->get($row->internet_plan_id);

            return [
                'id' => (int) $row->internet_plan_id,
                'name' => $plan?->name ?? 'Unknown plan',
                'company_id' => $plan?->company_id,
                'company_name' => $plan?->company?->name,
                'usage_count' => (int) $row->usage_count,
                'price' => $plan ? (float) $plan->price : null,
            ];
        })->values();

        $daily = RevenueRecord::query()
            ->where('recognized_at', '>=', $from)
            ->selectRaw('DATE(recognized_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $last14Days = collect(range(0, 13))->map(function (int $offset) use ($from, $daily) {
            $date = $from->copy()->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'total' => (float) ($daily[$date] ?? 0),
            ];
        })->values();

        $periodTotal = (float) $last14Days->sum('total');

        return $this->success([
            'currency' => $currency,
            'today_revenue' => $todayRevenue,
            'today_payments' => $todayPayments,
            'total_revenue' => $totalRevenue,
            'active_sessions' => $activeSessions,
            'routers_online' => $routersOnline,
            'routers_offline' => max(0, $routersTotal - $routersOnline),
            'routers_total' => $routersTotal,
            'recent_sessions' => $recentSessions,
            'top_plans' => $topPlans,
            'revenue_statistics' => [
                'period_days' => 14,
                'total' => $periodTotal,
                'last_14_days' => $last14Days,
            ],
        ], 'Admin dashboard retrieved');
    }
}
