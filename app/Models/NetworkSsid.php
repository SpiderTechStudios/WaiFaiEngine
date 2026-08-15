<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkSsid extends Model
{
    protected $fillable = [
        'company_id',
        'network_station_id',
        'network_device_id',
        'name',
        'ssid',
        'authentication_type',
        'captive_portal_enabled',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captive_portal_enabled' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function networkStation(): BelongsTo
    {
        return $this->belongsTo(NetworkStation::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function networkSessions(): HasMany
    {
        return $this->hasMany(NetworkSession::class);
    }

    public function captivePortals(): HasMany
    {
        return $this->hasMany(CaptivePortal::class);
    }
}
