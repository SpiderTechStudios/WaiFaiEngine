<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OmadaIntegration extends Model
{
    use SoftDeletes;

    public const DEPLOYMENT_SOFTWARE = 'software';

    public const DEPLOYMENT_HARDWARE = 'hardware';

    public const DEPLOYMENT_CLOUD = 'cloud';

    public const DEPLOYMENT_TYPES = [
        self::DEPLOYMENT_SOFTWARE,
        self::DEPLOYMENT_HARDWARE,
        self::DEPLOYMENT_CLOUD,
    ];

    public const CONNECTION_PUBLIC = 'public';

    public const CONNECTION_VPN = 'vpn';

    public const CONNECTION_CLOUD = 'cloud';

    public const CONNECTION_METHODS = [
        self::CONNECTION_PUBLIC,
        self::CONNECTION_VPN,
        self::CONNECTION_CLOUD,
    ];

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_UNREACHABLE = 'unreachable';

    public const STATUS_UNAUTHORIZED = 'unauthorized';

    public const STATUS_MISCONFIGURED = 'misconfigured';

    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'company_id',
        'network_device_id',
        'name',
        'deployment_type',
        'controller_url',
        'controller_host',
        'controller_port',
        'controller_id',
        'controller_version',
        'connection_method',
        'portal_key',
        'credentials',
        'verify_tls',
        'site_id',
        'site_name',
        'site_timezone',
        'connection_status',
        'status',
        'last_connected_at',
        'last_synced_at',
        'metadata',
    ];

    protected $hidden = [
        'credentials',
        'portal_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'controller_port' => 'integer',
            'verify_tls' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(OmadaSite::class);
    }

    public function accessPoints(): HasMany
    {
        return $this->hasMany(OmadaAccessPoint::class);
    }

    public function ssids(): HasMany
    {
        return $this->hasMany(OmadaSsid::class);
    }

    public function clientSessions(): HasMany
    {
        return $this->hasMany(OmadaClientSession::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials ?? [], $key, $default);
    }
}
