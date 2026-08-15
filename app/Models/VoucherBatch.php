<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherBatch extends Model
{
    protected $fillable = [
        'company_id',
        'internet_plan_id',
        'network_station_id',
        'name',
        'quantity',
        'unit_price',
        'currency',
        'created_by',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function internetPlan(): BelongsTo
    {
        return $this->belongsTo(InternetPlan::class);
    }

    public function networkStation(): BelongsTo
    {
        return $this->belongsTo(NetworkStation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function revenueRecords(): HasMany
    {
        return $this->hasMany(RevenueRecord::class);
    }
}
