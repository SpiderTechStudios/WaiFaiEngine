<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Company;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use App\Support\PaymentProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->filteredQuery($request, $this->currentCompany())
            ->with(['customer', 'internetPlan', 'networkStation'])
            ->paginate($this->perPage($request));

        return $this->success(
            $this->paginated($paginated, PaymentResource::collection($paginated->items())->resolve()),
            'Payments retrieved',
        );
    }

    /**
     * Mobile-money report summary for the current filters.
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->buildSummary(
            $this->filteredQuery($request, $this->currentCompany()),
            $this->staleMinutes(),
        );

        return $this->success($summary, 'Payment summary');
    }

    /**
     * CSV export for the current filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $company = $this->currentCompany();
        $timezone = $company->reportingTimezone();

        return $this->paymentsCsv(
            $this->filteredQuery($request, $company),
            (string) ($request->input('from') ?: now($timezone)->toDateString()),
            (string) ($request->input('to') ?: now($timezone)->toDateString()),
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new PaymentResource($payment))->resolve(), 'Payment recorded', 201);
    }

    public function show(PaymentTransaction $payment): JsonResponse
    {
        if ($payment->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        return $this->success(
            (new PaymentResource($payment->load(['customer', 'internetPlan', 'networkStation'])))->resolve(),
            'Payment retrieved',
        );
    }

    public function getAllPayments(Request $request): JsonResponse
    {
        $paginated = $this->allPaymentsQuery($request)
            ->with(['customer', 'internetPlan', 'company', 'networkStation'])
            ->paginate($this->perPage($request));

        return $this->success(
            $this->paginated($paginated, PaymentResource::collection($paginated->items())->resolve()),
            'All payments retrieved',
        );
    }

    public function getAllPaymentsSummary(Request $request): JsonResponse
    {
        return $this->success(
            $this->buildSummary($this->allPaymentsQuery($request), $this->staleMinutes()),
            'Payment summary',
        );
    }

    public function getAllPaymentsExport(Request $request): StreamedResponse
    {
        $companyId = $request->filled('company_id') ? $request->integer('company_id') : null;
        $timezone = $this->companyTimezone($companyId);

        return $this->paymentsCsv(
            $this->allPaymentsQuery($request),
            (string) ($request->input('from') ?: now($timezone)->toDateString()),
            (string) ($request->input('to') ?: now($timezone)->toDateString()),
        );
    }

    public function getPayment(PaymentTransaction $payment): JsonResponse
    {
        return $this->success(
            (new PaymentResource($payment->load(['customer', 'internetPlan', 'company', 'networkStation'])))->resolve(),
            'Payment retrieved',
        );
    }

    private function allPaymentsQuery(Request $request): Builder
    {
        $companyId = $request->filled('company_id') ? $request->integer('company_id') : null;

        $query = PaymentTransaction::query()
            ->when($companyId, fn (Builder $builder) => $builder->where('company_id', $companyId));

        return $this->applyFilters($query, $request, $this->companyTimezone($companyId));
    }

    private function companyTimezone(?int $companyId): string
    {
        $fallback = (string) config('platform.reporting_timezone', 'Africa/Dar_es_Salaam');

        if (! $companyId) {
            return $fallback;
        }

        return Company::query()->find($companyId)?->reportingTimezone() ?? $fallback;
    }

    private function filteredQuery(Request $request, Company $company): Builder
    {
        return $this->applyFilters(
            PaymentTransaction::query()->where('company_id', $company->id),
            $request,
            $company->reportingTimezone(),
        );
    }

    private function applyFilters(Builder $query, Request $request, string $timezone): Builder
    {
        $this->applyStatus($query, $request);
        $this->applySearch($query, $request);
        $this->applyProvider($query, $request);
        $this->applyPackage($query, $request);
        $this->applyDateRange($query, $request, $timezone);
        $this->applySort($query, $request);

        return $query;
    }

    private function applyStatus(Builder $query, Request $request): void
    {
        $raw = trim((string) $request->input('status', ''));

        if ($raw === '') {
            return;
        }

        $aliases = [];
        foreach (explode(',', $raw) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $aliases = array_merge($aliases, PaymentProvider::statusAliases($token));
        }

        if ($aliases !== []) {
            $query->whereIn(DB::raw('LOWER(status)'), array_values(array_unique($aliases)));
        }
    }

    private function applySearch(Builder $query, Request $request): void
    {
        $term = trim((string) $request->input('q', $request->input('search', '')));

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $digits = preg_replace('/\D+/', '', $term) ?: '';
        $phoneTerms = [];

        if (strlen($digits) >= 3) {
            $phoneTerms[] = '%'.$digits.'%';
            if (str_starts_with($digits, '0')) {
                $phoneTerms[] = '%255'.substr($digits, 1).'%';
            } elseif (str_starts_with($digits, '255')) {
                $phoneTerms[] = '%0'.substr($digits, 3).'%';
            } else {
                $phoneTerms[] = '%0'.$digits.'%';
            }
        }

        $query->where(function (Builder $builder) use ($like, $phoneTerms): void {
            $builder->where('reference', 'like', $like)
                ->orWhere('external_reference', 'like', $like)
                ->orWhereHas('customer', function (Builder $customer) use ($like, $phoneTerms): void {
                    $customer->where('name', 'like', $like);

                    foreach ($phoneTerms as $phoneTerm) {
                        $customer->orWhere('phone', 'like', $phoneTerm);
                    }
                });
        });
    }

    private function applyProvider(Builder $query, Request $request): void
    {
        $key = strtolower(trim((string) $request->input('provider', '')));

        if ($key === '') {
            return;
        }

        $query->whereIn(DB::raw('LOWER(payment_method)'), PaymentProvider::aliases($key));
    }

    private function applyPackage(Builder $query, Request $request): void
    {
        if ($request->filled('package_id')) {
            $query->where('internet_plan_id', $request->integer('package_id'));
        }
    }

    private function applyDateRange(Builder $query, Request $request, string $timezone): void
    {
        foreach (['from' => 'startOfDay', 'to' => 'endOfDay'] as $field => $boundary) {
            $value = (string) $request->input($field, '');

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                continue;
            }

            $moment = Carbon::parse($value, $timezone)->{$boundary}()->utc();
            $query->where('created_at', $field === 'from' ? '>=' : '<=', $moment);
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->input('sort', '-created_at');
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (! in_array($column, ['created_at', 'paid_at', 'amount', 'status'], true)) {
            $column = 'created_at';
            $descending = true;
        }

        $query->orderBy($column, $descending ? 'desc' : 'asc')
            ->orderBy('id', $descending ? 'desc' : 'asc');
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 15)));
    }

    private function staleMinutes(): int
    {
        return max(1, (int) config('platform.pending_payment_ttl_minutes', 30));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(Builder $query, int $staleMinutes): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw('LOWER(status) as status_key, COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach (PaymentProvider::statusKeys() as $key) {
            $byStatus[$key] = ['count' => 0, 'amount' => 0.0];
        }

        foreach ($rows as $row) {
            $key = PaymentProvider::statusKey((string) $row->status_key);
            $byStatus[$key]['count'] += (int) $row->total;
            $byStatus[$key]['amount'] = round($byStatus[$key]['amount'] + (float) $row->amount, 2);
        }

        $stalePending = (int) (clone $query)
            ->reorder()
            ->whereIn(DB::raw('LOWER(status)'), PaymentProvider::statusAliases('pending'))
            ->where('created_at', '<', now()->subMinutes($staleMinutes))
            ->count();

        $paid = $byStatus['paid'];
        $failed = $byStatus['failed'];
        $settled = $paid['count'] + $failed['count'] + $stalePending;

        return [
            'paid' => $paid,
            'pending' => [
                'count' => $byStatus['pending']['count'],
                'amount' => $byStatus['pending']['amount'],
                'stale_count' => $stalePending,
            ],
            'failed' => $failed,
            'expired' => $byStatus['expired'],
            'cancelled' => $byStatus['cancelled'],
            'refunded' => $byStatus['refunded'],
            'other' => $byStatus['other'],
            'by_status' => array_map(fn (array $row): int => (int) $row['count'], $byStatus),
            'success_rate' => $settled > 0 ? round($paid['count'] / $settled, 4) : null,
            'avg_ticket' => $paid['count'] > 0 ? round($paid['amount'] / $paid['count'], 2) : null,
            'stale_after_minutes' => $staleMinutes,
            'currency' => (string) config('platform.currency', 'TZS'),
        ];
    }

    private function paymentsCsv(Builder $query, string $from, string $to): StreamedResponse
    {
        $query->reorder()
            ->with(['customer', 'internetPlan', 'networkStation'])
            ->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'reference',
                'created_at',
                'paid_at',
                'status',
                'amount',
                'currency',
                'customer_name',
                'phone',
                'package',
                'provider',
                'provider_transaction_id',
                'branch',
                'failure_reason',
            ]);

            $query->chunkById(500, function ($payments) use ($out): void {
                foreach ($payments as $payment) {
                    $metadata = is_array($payment->metadata) ? $payment->metadata : [];

                    fputcsv($out, [
                        $payment->reference,
                        optional($payment->created_at)->toIso8601String(),
                        optional($payment->paid_at)->toIso8601String(),
                        $payment->status,
                        $payment->amount,
                        $payment->currency,
                        $payment->customer?->name,
                        $payment->customer?->phone,
                        $payment->internetPlan?->name,
                        PaymentProvider::key($payment->payment_method, $metadata['network'] ?? null),
                        $payment->external_reference,
                        $payment->networkStation?->name,
                        $metadata['failure_reason'] ?? $metadata['provider_message'] ?? null,
                    ]);
                }
            });

            fclose($out);
        }, 'payments-'.$from.'_'.$to.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
