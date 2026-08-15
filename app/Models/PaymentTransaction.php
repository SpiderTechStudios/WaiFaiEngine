<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'internet_plan_id',
        'plan_price_id',
        'payment_gateway_id',
        'network_station_id',
        'voucher_id',
        'reference',
        'external_reference',
        'amount',
        'currency',
        'payment_method',
        'status',
        'initiated_at',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function internetPlan(): BelongsTo
    {
        return $this->belongsTo(InternetPlan::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function networkStation(): BelongsTo
    {
        return $this->belongsTo(NetworkStation::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function revenueRecords(): HasMany
    {
        return $this->hasMany(RevenueRecord::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
