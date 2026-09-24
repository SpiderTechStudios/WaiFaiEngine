<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Offer extends Model
{
    public const DURATION_UNITS = [
        'HOURS',
        'DAYS',
        'WEEKS',
        'MONTHS',
    ];

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'duration',
        'duration_unit',
        'max_claims',
        'claims_count',
        'is_active',
        'internet_plan_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration' => 'integer',
            'max_claims' => 'integer',
            'claims_count' => 'integer',
            'is_active' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function routers(): BelongsToMany
    {
        return $this->belongsToMany(NetworkDevice::class, 'offer_network_device')->withTimestamps();
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OfferClaim::class);
    }

    public function isWithinWindow(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->starts_at && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function hasRemainingClaims(): bool
    {
        return $this->max_claims === null || $this->claims_count < $this->max_claims;
    }

    public function remainingClaims(): ?int
    {
        return $this->max_claims === null
            ? null
            : max(0, $this->max_claims - $this->claims_count);
    }

    /**
     * @param  Collection<int, NetworkDevice>|null  $routers
     */
    public function appliesToRouter(?int $routerId, $routers = null): bool
    {
        $routers ??= $this->relationLoaded('routers') ? $this->routers : $this->routers()->get();

        if ($routers->isEmpty()) {
            return true;
        }

        if (! $routerId) {
            return false;
        }

        return $routers->contains(fn (NetworkDevice $router) => (int) $router->id === (int) $routerId);
    }
}
