<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCES = [
        self::SOURCE_MANUAL,
        self::SOURCE_SYSTEM,
    ];

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_VOID = 'void';

    /**
     * Whitelisted sort columns for the list endpoint.
     *
     * @var list<string>
     */
    public const SORTABLE = ['paid_at', 'amount', 'created_at', 'id'];

    protected $fillable = [
        'company_id',
        'router_id',
        'expense_type_id',
        'category',
        'source',
        'source_type',
        'source_id',
        'description',
        'amount',
        'currency',
        'paid_at',
        'paid_to',
        'reference',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function isSystemGenerated(): bool
    {
        return $this->source === self::SOURCE_SYSTEM;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'router_id');
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
