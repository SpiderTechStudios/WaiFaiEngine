<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\RevenueRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $this->currentCompany();
        $today = Carbon::today();

        $todayRevenue = RevenueRecord::query()
            ->where('company_id', $company->id)
            ->whereDate('recognized_at', $today)
            ->sum('amount');

        $totalRevenue = RevenueRecord::query()
            ->where('company_id', $company->id)
            ->sum('amount');

        $todayPayments = PaymentTransaction::query()
            ->where('company_id', $company->id)
            ->where('status', 'paid')
            ->whereDate('paid_at', $today)
            ->count();

        $activeSessions = NetworkSession::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->count();

        $routers = NetworkDevice::query()
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->get();

        $routersOnline = $routers->filter(fn (NetworkDevice $router) => $router->last_seen_at?->gt(now()->subMinutes(10)))->count();

        $recentSessions = NetworkSession::query()
            ->with(['customer', 'networkDevice'])
            ->where('company_id', $company->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return $this->success([
            'currency' => 'TZS',
            'today_revenue' => (float) $todayRevenue,
            'today_payments' => $todayPayments,
            'total_revenue' => (float) $totalRevenue,
            'active_sessions' => $activeSessions,
            'routers_online' => $routersOnline,
            'routers_total' => $routers->count(),
            'recent_sessions' => $recentSessions->map(fn (NetworkSession $session) => [
                'id' => $session->id,
                'mac_address' => $session->mac_address,
                'status' => $session->status,
                'description' => ($session->customer?->name ?? 'No plan yet').' - '.$company->name,
            ])->values(),
        ], 'Dashboard retrieved');
    }
}
