<?php

namespace App\Queries;

use App\Models\Expense;
use App\Models\ExpenseType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable, controller-agnostic expense filtering.
 *
 * Used by the expense list, the expense summary, and the router expense report so
 * every endpoint honours exactly the same filter semantics.
 */
class ExpenseQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function apply(Builder $query, array $filters, bool $withSorting = true): Builder
    {
        $query = $query
            ->when(
                self::category($filters),
                fn (Builder $builder, string $category) => $builder->where('category', $category),
            )
            ->when(
                self::routerFilter($filters) === 'business',
                fn (Builder $builder) => $builder->whereNull('router_id'),
            )
            ->when(
                self::routerFilter($filters) === 'router',
                fn (Builder $builder) => $builder->whereNotNull('router_id'),
            )
            ->when(
                is_int(self::routerId($filters)),
                fn (Builder $builder) => $builder->where('router_id', self::routerId($filters)),
            )
            ->when(
                self::filledInt($filters, 'expense_type_id') !== null,
                fn (Builder $builder) => $builder->where('expense_type_id', self::filledInt($filters, 'expense_type_id')),
            )
            ->when(
                self::filled($filters, 'from'),
                fn (Builder $builder) => $builder->whereDate('paid_at', '>=', $filters['from']),
            )
            ->when(
                self::filled($filters, 'to'),
                fn (Builder $builder) => $builder->whereDate('paid_at', '<=', $filters['to']),
            )
            ->when(
                self::filled($filters, 'paid_to'),
                fn (Builder $builder) => $builder->where('paid_to', 'like', '%'.$filters['paid_to'].'%'),
            )
            ->when(
                self::filled($filters, 'currency'),
                fn (Builder $builder) => $builder->where('currency', strtoupper((string) $filters['currency'])),
            )
            ->when(
                in_array(($filters['source'] ?? null), Expense::SOURCES, true),
                fn (Builder $builder) => $builder->where('source', $filters['source']),
            )
            ->when(
                self::filled($filters, 'search'),
                function (Builder $builder) use ($filters) {
                    $term = '%'.$filters['search'].'%';

                    $builder->where(function (Builder $inner) use ($term) {
                        $inner->where('description', 'like', $term)
                            ->orWhere('paid_to', 'like', $term)
                            ->orWhere('reference', 'like', $term)
                            ->orWhere('notes', 'like', $term);
                    });
                },
            );

        if ($withSorting) {
            $query->orderBy(self::sortColumn($filters), self::sortDirection($filters))
                ->orderByDesc('id');
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function category(array $filters): ?string
    {
        $category = strtolower((string) ($filters['category'] ?? ''));

        return in_array($category, ExpenseType::CATEGORIES, true) ? $category : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return 'business'|'router'|null
     */
    private static function routerFilter(array $filters): ?string
    {
        $scope = strtolower((string) ($filters['scope'] ?? ''));

        if ($scope === 'business') {
            return 'business';
        }

        if ($scope === 'router') {
            return 'router';
        }

        if (! array_key_exists('router_id', $filters)) {
            return null;
        }

        $value = $filters['router_id'];

        if ($value === null || $value === '' || $value === 'null') {
            return 'business';
        }

        return 'router';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function routerId(array $filters): ?int
    {
        if (self::routerFilter($filters) !== 'router') {
            return null;
        }

        $value = $filters['router_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function filledInt(array $filters, string $key): ?int
    {
        $value = $filters[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function filled(array $filters, string $key): bool
    {
        $value = $filters[$key] ?? null;

        return $value !== null && $value !== '';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function sortColumn(array $filters): string
    {
        $sort = (string) ($filters['sort'] ?? '');

        return in_array($sort, Expense::SORTABLE, true) ? $sort : 'paid_at';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function sortDirection(array $filters): string
    {
        return strtolower((string) ($filters['direction'] ?? '')) === 'asc' ? 'asc' : 'desc';
    }
}
