<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternetPlan extends Model
{
    use SoftDeletes;

    public const DURATION_UNITS = [
        'HOURS',
        'DAYS',
        'WEEKS',
        'MONTHS',
        'UNLIMITED_DATA',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'badge',
        'description',
        'duration',
        'duration_unit',
        'price',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stationPlans(): HasMany
    {
        return $this->hasMany(StationPlan::class);
    }

    public function networkStations(): BelongsToMany
    {
        return $this->belongsToMany(NetworkStation::class, 'station_plans')
            ->withPivot(['company_id', 'status'])
            ->withTimestamps();
    }

    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
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
