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

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'duration',
        'duration_unit',
        'data_limit',
        'data_limit_unit',
        'download_speed',
        'upload_speed',
        'speed_unit',
        'max_devices',
        'activation_mode',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
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
