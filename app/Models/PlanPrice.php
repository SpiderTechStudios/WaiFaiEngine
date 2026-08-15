<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    protected $fillable = [
        'company_id',
        'internet_plan_id',
        'currency',
        'amount',
        'status',
        'valid_from',
        'valid_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
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

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }
}
