<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\PlatformBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminBillingController extends Controller
{
    public function __construct(private PlatformBillingService $billing) {}

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return $this->success(
            $this->billing->summary($from, $to),
            'Platform billing summary retrieved',
        );
    }

    public function clients(Request $request): JsonResponse
    {
        $result = $this->billing->clients([
            'search' => (string) $request->query('search', ''),
            'subscription_status' => (string) $request->query('subscription_status', ''),
            'expiring_within' => $request->filled('expiring_within') ? $request->integer('expiring_within') : null,
            'sort' => (string) $request->query('sort', 'period_ends'),
        ], $request->integer('per_page', 15), $request->integer('page', 1));

        return $this->success($result, 'Clients retrieved');
    }

    public function client(Company $company): JsonResponse
    {
        return $this->success($this->billing->client($company), 'Client retrieved');
    }

    public function renewals(Request $request): JsonResponse
    {
        $days = min(90, max(1, $request->integer('days', PlatformBillingService::DUE_SOON_DAYS)));

        return $this->success($this->billing->renewals($days), 'Renewals retrieved');
    }

    public function payments(Request $request): JsonResponse
    {
        $result = $this->billing->payments([
            'company_id' => $request->filled('company_id') ? $request->integer('company_id') : null,
            'purpose' => $request->filled('purpose') ? (string) $request->query('purpose') : null,
            'status' => $request->filled('status') ? (string) $request->query('status') : null,
            'from' => $request->filled('from') ? (string) $request->query('from') : null,
            'to' => $request->filled('to') ? (string) $request->query('to') : null,
        ], $request->integer('per_page', 15), $request->integer('page', 1));

        return $this->success($result, 'Platform payments retrieved');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $timezone = (string) config('platform.reporting_timezone', 'Africa/Dar_es_Salaam');
        $now = Carbon::now($timezone);
        $today = $now->copy()->startOfDay();

        $to = $request->filled('to')
            ? Carbon::createFromFormat('Y-m-d', (string) $request->query('to'), $timezone)->startOfDay()
            : $today->copy();

        $from = $request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', (string) $request->query('from'), $timezone)->startOfDay()
            : $to->copy()->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
