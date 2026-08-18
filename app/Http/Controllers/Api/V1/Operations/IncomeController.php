<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Models\RevenueRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $this->currentCompany();
        $from = Carbon::today()->subDays(13)->startOfDay();

        $bySource = RevenueRecord::query()
            ->where('company_id', $company->id)
            ->selectRaw('source, SUM(amount) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $daily = RevenueRecord::query()
            ->where('company_id', $company->id)
            ->where('recognized_at', '>=', $from)
            ->selectRaw('DATE(recognized_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $this->success([
            'currency' => 'TZS',
            'by_source' => [
                'mobile_money' => (float) ($bySource['mobile_money'] ?? 0),
                'voucher' => (float) ($bySource['voucher'] ?? 0),
            ],
            'last_14_days' => $daily->map(fn ($row) => [
                'date' => $row->day,
                'total' => (float) $row->total,
            ])->values(),
            'total' => (float) RevenueRecord::query()->where('company_id', $company->id)->sum('amount'),
        ], 'Income retrieved');
    }
}
