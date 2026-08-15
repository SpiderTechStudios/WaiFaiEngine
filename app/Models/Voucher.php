<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'company_id',
        'voucher_batch_id',
        'internet_plan_id',
        'code',
        'status',
        'activated_at',
        'redeemed_at',
        'expires_at',
        'customer_id',
        'access_grant_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function voucherBatch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class);
    }

    public function internetPlan(): BelongsTo
    {
        return $this->belongsTo(InternetPlan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function accessGrant(): BelongsTo
    {
        return $this->belongsTo(AccessGrant::class);
    }

    public function revenueRecords(): HasMany
    {
        return $this->hasMany(RevenueRecord::class);
    }
}
