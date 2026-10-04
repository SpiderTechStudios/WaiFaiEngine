<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\IncomeReportRequest;
use App\Models\Company;
use App\Models\RevenueRecord;
use App\Services\IncomeReportService;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeController extends Controller
{
    public function __construct(private IncomeReportService $incomeReport) {}

    public function index(IncomeReportRequest $request): JsonResponse
    {
        $company = $this->currentCompany();

        return $this->success([
            ...$this->report($request, $company),
            ...$this->legacy($company),
        ], 'Income retrieved');
    }

    public function export(IncomeReportRequest $request): Response|StreamedResponse
    {
        $request->validate(['format' => ['required', 'in:csv,pdf']]);

        $company = $this->currentCompany();
        $report = $this->report($request, $company);
        $filename = 'income-'.$report['range']['from'].'_'.$report['range']['to'];

        if ($request->input('format') === 'pdf') {
            $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
            $dompdf->loadHtml(view('reports.income-pdf', [
                'company' => $company,
                'report' => $report,
            ])->render());
            $dompdf->setPaper('A4');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'mobile_money', 'vouchers', 'total', 'transactions']);

            foreach ($report['daily'] as $day) {
                fputcsv($out, [$day['date'], $day['mobile_money'], $day['vouchers'], $day['total'], $day['count']]);
            }

            fputcsv($out, [
                'TOTAL',
                $report['totals']['mobile_money'],
                $report['totals']['vouchers'],
                $report['totals']['gross'],
                $report['counts']['paid'],
            ]);
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(IncomeReportRequest $request, Company $company): array
    {
        [$from, $to] = $request->range($company->reportingTimezone());

        return $this->incomeReport->build($company, $from, $to, $request->boolean('compare'), [
            'branch_id' => $request->filled('branch_id') ? $request->integer('branch_id') : null,
            'router_id' => $request->filled('router_id') ? $request->integer('router_id') : null,
        ]);
    }

    /**
     * Previous response shape, unchanged until the frontend switches.
     *
     * @return array<string, mixed>
     */
    private function legacy(Company $company): array
    {
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

        return [
            'currency' => 'TZS',
            'total' => (float) RevenueRecord::query()->where('company_id', $company->id)->sum('amount'),
            'by_source' => [
                'mobile_money' => (float) ($bySource['mobile_money'] ?? 0),
                'voucher' => (float) ($bySource['voucher'] ?? 0),
            ],
            'last_14_days' => $daily->map(fn ($row) => [
                'date' => $row->day,
                'total' => (float) $row->total,
            ])->values(),
        ];
    }
}
