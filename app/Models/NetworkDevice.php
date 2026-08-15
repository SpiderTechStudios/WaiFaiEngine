<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkDevice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'network_station_id',
        'type',
        'vendor',
        'model',
        'name',
        'serial_number',
        'mac_address',
        'ip_address',
        'status',
        'last_seen_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
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

    public function connectionMappings(): HasMany
    {
        return $this->hasMany(NetworkConnectionDevice::class);
    }

    public function networkSsids(): HasMany
    {
        return $this->hasMany(NetworkSsid::class);
    }

    public function networkSessions(): HasMany
    {
        return $this->hasMany(NetworkSession::class);
    }
}
