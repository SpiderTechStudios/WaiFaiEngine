<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkStation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'location_id',
        'name',
        'code',
        'description',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function networkConnections(): HasMany
    {
        return $this->hasMany(NetworkConnection::class);
    }

    public function captivePortals(): HasMany
    {
        return $this->hasMany(CaptivePortal::class);
    }

    public function networkDevices(): HasMany
    {
        return $this->hasMany(NetworkDevice::class);
    }

    public function networkSsids(): HasMany
    {
        return $this->hasMany(NetworkSsid::class);
    }

    public function internetPlans(): BelongsToMany
    {
        return $this->belongsToMany(InternetPlan::class, 'station_plans')
            ->withPivot(['company_id', 'status'])
            ->withTimestamps();
    }

    public function stationPlans(): HasMany
    {
        return $this->hasMany(StationPlan::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function networkSessions(): HasMany
    {
        return $this->hasMany(NetworkSession::class);
    }
}
