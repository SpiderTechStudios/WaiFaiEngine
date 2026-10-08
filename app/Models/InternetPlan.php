<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternetPlan extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_DRAFT = 'draft';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_DRAFT,
    ];

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
        'speed_download_mbps',
        'speed_upload_mbps',
        'data_cap_mb',
        'devices_allowed',
        'status',
        'sort_order',
        'visible_on_portal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration' => 'integer',
            'speed_download_mbps' => 'integer',
            'speed_upload_mbps' => 'integer',
            'data_cap_mb' => 'integer',
            'devices_allowed' => 'integer',
            'sort_order' => 'integer',
            'visible_on_portal' => 'boolean',
        ];
    }

    /** Portal ordering: explicit sort order first, then newest. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Packages the captive portal may show. */
    public function scopeVisibleOnPortal(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('visible_on_portal', true);
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
