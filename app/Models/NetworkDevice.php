<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkDevice extends Model
{
    use SoftDeletes;

    public const GATEWAY_MIKROTIK = 'mikrotik';

    public const GATEWAY_RUIJIE = 'ruijie';

    public const GATEWAY_WAVLINK = 'wavlink';

    public const GATEWAY_TYPES = [
        self::GATEWAY_MIKROTIK,
        self::GATEWAY_RUIJIE,
        self::GATEWAY_WAVLINK,
    ];

    protected $fillable = [
        'company_id',
        'network_station_id',
        'type',
        'gateway_type',
        'name',
        'lan_ip',
        'api_host',
        'api_port',
        'api_username',
        'api_password',
        'gateway_id',
        'serial_number',
        'wifidog_port',
        'source',
        'installation_request_id',
        'status',
        'last_seen_at',
    ];

    protected $hidden = [
        'api_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'wifidog_port' => 'integer',
            'last_seen_at' => 'datetime',
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

    public function captiveSessions(): HasMany
    {
        return $this->hasMany(CaptiveSession::class);
    }
}
