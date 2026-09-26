<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\NetworkDevice;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Queries\ExpenseQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): Expense
    {
        $type = $this->resolveActiveType($data['expense_type_id'] ?? null);
        $routerId = $this->resolveRouterId($company->id, $data['router_id'] ?? null);

        return DB::transaction(function () use ($company, $type, $routerId, $data, $actor) {
            $expense = Expense::query()->create([
                'company_id' => $company->id,
                'router_id' => $routerId,
                'expense_type_id' => $type->id,
                'category' => $type->category,
                'source' => Expense::SOURCE_MANUAL,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'currency' => $this->normalizeCurrency($data['currency'] ?? null),
                'paid_at' => $data['paid_at'],
                'paid_to' => $data['paid_to'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Expense::STATUS_RECORDED,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->auditLogger->log('expense_created', $actor, $company->id, Expense::class, $expense->id);

            return $expense->load(['expenseType', 'router', 'creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data, User $actor): Expense
    {
        $this->assertEditable($expense);

        $attributes = ['updated_by' => $actor->id];

        if (array_key_exists('expense_type_id', $data)) {
            $type = $this->resolveActiveType($data['expense_type_id']);
            $attributes['expense_type_id'] = $type->id;
            $attributes['category'] = $type->category;
        }

        if (array_key_exists('router_id', $data)) {
            $attributes['router_id'] = $this->resolveRouterId($expense->company_id, $data['router_id']);
        }

        foreach (['description', 'amount', 'paid_at', 'paid_to', 'reference', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('currency', $data)) {
            $attributes['currency'] = $this->normalizeCurrency($data['currency']);
        }

        return DB::transaction(function () use ($expense, $attributes, $actor) {
            $expense->fill($attributes)->save();

            $this->auditLogger->log('expense_updated', $actor, $expense->company_id, Expense::class, $expense->id);

            return $expense->fresh(['expenseType', 'router', 'creator']);
        });
    }

    public function delete(Expense $expense, User $actor): void
    {
        $this->assertEditable($expense);

        DB::transaction(function () use ($expense, $actor) {
            $this->auditLogger->log('expense_deleted', $actor, $expense->company_id, Expense::class, $expense->id);

            $expense->delete();
        });
    }

    /**
     * Record money spent that originated from another system transaction
     * (platform subscriptions, future automated charges). The originating
     * transaction stays the source of truth via source_type / source_id.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordSystemExpense(
        Company $company,
        ExpenseType $type,
        array $data,
        ?User $actor = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): Expense {
        return DB::transaction(function () use ($company, $type, $data, $actor, $sourceType, $sourceId) {
            $expense = Expense::query()->create([
                'company_id' => $company->id,
                'router_id' => $this->resolveRouterId($company->id, $data['router_id'] ?? null),
                'expense_type_id' => $type->id,
                'category' => $type->category,
                'source' => Expense::SOURCE_SYSTEM,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'currency' => $this->normalizeCurrency($data['currency'] ?? null),
                'paid_at' => $data['paid_at'],
                'paid_to' => $data['paid_to'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Expense::STATUS_RECORDED,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->auditLogger->log('expense_recorded', $actor, $company->id, Expense::class, $expense->id);

            return $expense->load(['expenseType', 'router', 'creator']);
        });
    }

    /**
     * Create the accounting view of an existing platform payment without
     * duplicating the underlying money movement. Idempotent per payment.
     */
    public function recordFromPlatformPayment(Company $company, PlatformPayment $payment): ?Expense
    {
        $existing = Expense::query()
            ->where('company_id', $company->id)
            ->where('source_type', PlatformPayment::class)
            ->where('source_id', $payment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $type = $this->platformExpenseType();

        if (! $type) {
            return null;
        }

        return $this->recordSystemExpense(
            company: $company,
            type: $type,
            data: [
                'description' => 'WaiFai platform payment '.$payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
                'paid_to' => 'WaiFai',
                'reference' => $payment->reference,
            ],
            sourceType: PlatformPayment::class,
            sourceId: $payment->id,
        );
    }

    /**
     * Aggregate totals for the current filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(Company $company, array $filters, ?int $routerId = null): array
    {
        $query = Expense::query()->where('company_id', $company->id);

        if ($routerId !== null) {
            $query->where('router_id', $routerId);
        }

        $rows = ExpenseQuery::apply($query, $filters, withSorting: false)
            ->selectRaw('currency, category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('currency', 'category')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $currency = (string) $row->currency;
            $amount = round((float) $row->total, 2);

            $grouped[$currency] ??= [
                'currency' => $currency,
                'total' => 0.0,
                'platform_total' => 0.0,
                'operational_total' => 0.0,
                'count' => 0,
            ];

            $grouped[$currency]['total'] = round($grouped[$currency]['total'] + $amount, 2);
            $grouped[$currency]['count'] += (int) $row->count;

            if ($row->category === ExpenseType::CATEGORY_PLATFORM) {
                $grouped[$currency]['platform_total'] = round($grouped[$currency]['platform_total'] + $amount, 2);
            } else {
                $grouped[$currency]['operational_total'] = round($grouped[$currency]['operational_total'] + $amount, 2);
            }
        }

        $byCurrency = array_values($grouped);

        $summary = [
            'total' => 0.0,
            'currency' => null,
            'platform_total' => 0.0,
            'operational_total' => 0.0,
            'count' => 0,
            'by_currency' => $byCurrency,
        ];

        if (count($byCurrency) === 1) {
            $summary['total'] = $byCurrency[0]['total'];
            $summary['currency'] = $byCurrency[0]['currency'];
            $summary['platform_total'] = $byCurrency[0]['platform_total'];
            $summary['operational_total'] = $byCurrency[0]['operational_total'];
            $summary['count'] = $byCurrency[0]['count'];
        } elseif (count($byCurrency) > 1) {
            // Never silently add different currencies together.
            $summary['total'] = null;
            $summary['platform_total'] = null;
            $summary['operational_total'] = null;
            $summary['count'] = array_sum(array_column($byCurrency, 'count'));
        }

        if ($summary['currency'] === null && ! empty($filters['currency'])) {
            $summary['currency'] = strtoupper((string) $filters['currency']);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{summary: array<string, mixed>, breakdown: list<array<string, mixed>>}
     */
    public function routerReport(Company $company, NetworkDevice $router, array $filters): array
    {
        $breakdown = ExpenseQuery::apply(
            Expense::query()->where('company_id', $company->id)->where('router_id', $router->id),
            $filters,
            withSorting: false,
        )
            ->join('expense_types', 'expense_types.id', '=', 'expenses.expense_type_id')
            ->selectRaw('expense_types.id as expense_type_id, expense_types.name as expense_type, expense_types.category as category, SUM(expenses.amount) as total, COUNT(*) as count')
            ->groupBy('expense_types.id', 'expense_types.name', 'expense_types.category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'expense_type_id' => (int) $row->expense_type_id,
                'expense_type' => $row->expense_type,
                'category' => $row->category,
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->count,
            ])
            ->all();

        return [
            'summary' => $this->summary($company, $filters, $router->id),
            'breakdown' => $breakdown,
        ];
    }

    private function platformExpenseType(): ?ExpenseType
    {
        return ExpenseType::query()
            ->where('category', ExpenseType::CATEGORY_PLATFORM)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    private function resolveActiveType(mixed $typeId): ExpenseType
    {
        $type = is_numeric($typeId) ? ExpenseType::query()->find((int) $typeId) : null;

        if (! $type) {
            throw ValidationException::withMessages([
                'expense_type_id' => ['The selected expense type is invalid.'],
            ]);
        }

        if (! $type->is_active) {
            throw ValidationException::withMessages([
                'expense_type_id' => ['The selected expense type is inactive.'],
            ]);
        }

        return $type;
    }

    private function resolveRouterId(int $companyId, mixed $routerId): ?int
    {
        if ($routerId === null || $routerId === '' || $routerId === 'null') {
            return null;
        }

        if (! is_numeric($routerId)) {
            throw ValidationException::withMessages([
                'router_id' => ['The selected router is invalid.'],
            ]);
        }

        $id = (int) $routerId;

        $exists = NetworkDevice::query()
            ->where('company_id', $companyId)
            ->where('type', 'router')
            ->whereKey($id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'router_id' => ['The selected router does not belong to your account.'],
            ]);
        }

        return $id;
    }

    private function normalizeCurrency(mixed $currency): string
    {
        $currency = trim((string) ($currency ?? ''));

        if ($currency === '') {
            $currency = (string) config('platform.currency', 'TZS');
        }

        return strtoupper($currency);
    }

    private function assertEditable(Expense $expense): void
    {
        if ($expense->isSystemGenerated()) {
            abort(403, 'System-generated expenses cannot be modified.');
        }
    }
}
