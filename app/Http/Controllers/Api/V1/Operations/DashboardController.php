<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\NetworkSession;
use App\Models\RevenueRecord;
use App\Models\Voucher;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $this->currentCompany();
        $timezone = $company->reportingTimezone();
        $currency = (string) config('platform.currency', 'TZS');

        $now = Carbon::now($timezone);
        $todayStart = $now->copy()->startOfDay();
        $yesterdayStart = $todayStart->copy()->subDay();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();

        $today = $this->revenueBetween($company, $todayStart, $now);
        $thisMonth = $this->revenueBetween($company, $monthStart, $now);
        $allTime = $this->revenueBetween($company, null, null);

        $wallet = Wallet::query()->where('company_id', $company->id)->where('currency', $currency)->first();
        $pendingWithdrawals = Withdrawal::query()
            ->where('company_id', $company->id)
            ->where('status', Withdrawal::STATUS_PENDING)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $activeSessions = NetworkSession::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->count();

        $recentSessions = NetworkSession::query()
            ->with(['customer', 'internetPlan'])
            ->where('company_id', $company->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return $this->success([
            'currency' => $currency,
            'timezone' => $timezone,
            'generated_at' => $now->toIso8601String(),

            'revenue' => [
                'today' => $today,
                'yesterday' => $this->revenueBetween($company, $yesterdayStart, $todayStart),
                'this_month' => $thisMonth,
                'last_month' => $this->revenueBetween($company, $lastMonthStart, $monthStart),
                'all_time' => $allTime,
            ],

            'sales_today' => [
                'mobile_money_payments' => $today['mobile_money_count'],
                'vouchers_sold' => $today['voucher_count'],
                'offers_claimed' => AccessGrant::query()
                    ->where('company_id', $company->id)
                    ->where('source', 'offer')
                    ->whereBetween('created_at', [$todayStart->copy()->utc(), $now->copy()->utc()])
                    ->count(),
            ],

            'sessions' => [
                'active' => $activeSessions,
                'customers_with_active_access' => AccessGrant::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->distinct('customer_id')
                    ->count('customer_id'),
                'expiring_within_hour' => AccessGrant::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'active')
                    ->whereBetween('expires_at', [now(), now()->addHour()])
                    ->count(),
            ],

            'customers' => [
                'total' => Customer::query()->where('company_id', $company->id)->count(),
                'new_today' => Customer::query()
                    ->where('company_id', $company->id)
                    ->whereBetween('created_at', [$todayStart->copy()->utc(), $now->copy()->utc()])
                    ->count(),
                'new_this_month' => Customer::query()
                    ->where('company_id', $company->id)
                    ->whereBetween('created_at', [$monthStart->copy()->utc(), $now->copy()->utc()])
                    ->count(),
            ],

            'vouchers' => [
                'unused' => Voucher::query()
                    ->where('company_id', $company->id)
                    ->where('status', Voucher::STATUS_ACTIVE)
                    ->where('uses_count', 0)
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
                'sold_this_month' => $thisMonth['voucher_count'],
            ],

            'wallet' => [
                'balance' => (float) ($wallet?->balance ?? 0),
                'pending_withdrawals_count' => (int) ($pendingWithdrawals?->total ?? 0),
                'pending_withdrawals_amount' => (float) ($pendingWithdrawals?->amount ?? 0),
            ],

            'top_packages_this_month' => $this->topPackages($company, $monthStart, $now),

            'recent_sessions' => $recentSessions->map(fn (NetworkSession $session) => [
                'id' => $session->id,
                'mac_address' => $session->mac_address,
                'status' => $session->status,
                'customer' => $session->customer?->name,
                'package' => $session->internetPlan?->name,
                'started_at' => $session->started_at,
                'description' => ($session->customer?->name ?? 'No plan yet').' - '.$company->name,
            ])->values(),

            // Flat totals kept for clients still reading the previous shape.
            'today_revenue' => $today['total'],
            'today_payments' => $today['mobile_money_count'],
            'total_revenue' => $allTime['total'],
            'active_sessions' => $activeSessions,
        ], 'Dashboard retrieved');
    }

    /**
     * @return array{total: float, mobile_money: float, voucher: float, mobile_money_count: int, voucher_count: int}
     */
    private function revenueBetween(Company $company, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $rows = RevenueRecord::query()
            ->where('company_id', $company->id)
            ->where('status', 'recognized')
            ->when($from, fn ($query) => $query->where('recognized_at', '>=', $from->copy()->utc()))
            ->when($to, fn ($query) => $query->where('recognized_at', '<', $to->copy()->utc()->addSecond()))
            ->selectRaw('source, COUNT(*) as records, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        $mobileMoney = (float) ($rows->get('mobile_money')?->amount ?? 0);
        $voucher = (float) ($rows->get('voucher')?->amount ?? 0);

        return [
            'total' => round((float) $rows->sum('amount'), 2),
            'mobile_money' => $mobileMoney,
            'voucher' => $voucher,
            'mobile_money_count' => (int) ($rows->get('mobile_money')?->records ?? 0),
            'voucher_count' => (int) ($rows->get('voucher')?->records ?? 0),
        ];
    }

    /**
     * @return list<array{package_id: int, name: string, sales: int, revenue: float}>
     */
    private function topPackages(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        return RevenueRecord::query()
            ->from('revenue_records as rr')
            ->leftJoin('payment_transactions as pt', 'pt.id', '=', 'rr.payment_transaction_id')
            ->leftJoin('vouchers as v', 'v.id', '=', 'rr.voucher_id')
            ->join('internet_plans as ip', 'ip.id', '=', DB::raw('COALESCE(pt.internet_plan_id, v.internet_plan_id)'))
            ->where('rr.company_id', $company->id)
            ->where('rr.status', 'recognized')
            ->where('rr.recognized_at', '>=', $from->copy()->utc())
            ->where('rr.recognized_at', '<', $to->copy()->utc()->addSecond())
            ->groupBy('ip.id', 'ip.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get([
                'ip.id as package_id',
                'ip.name',
                DB::raw('COUNT(*) as sales'),
                DB::raw('SUM(rr.amount) as revenue'),
            ])
            ->map(fn ($row) => [
                'package_id' => (int) $row->package_id,
                'name' => $row->name,
                'sales' => (int) $row->sales,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }
}
