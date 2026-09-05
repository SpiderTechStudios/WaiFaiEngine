<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptiveSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHENTICATED = 'authenticated';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'company_id',
        'network_device_id',
        'network_station_id',
        'gateway_id',
        'client_mac',
        'client_ip',
        'ssid',
        'gw_address',
        'gw_port',
        'requested_url',
        'token',
        'status',
        'access_grant_id',
        'network_session_id',
        'authenticated_at',
        'expires_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gw_port' => 'integer',
            'authenticated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
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

    public function networkStation(): BelongsTo
    {
        return $this->belongsTo(NetworkStation::class);
    }

    public function accessGrant(): BelongsTo
    {
        return $this->belongsTo(AccessGrant::class);
    }

    public function networkSession(): BelongsTo
    {
        return $this->belongsTo(NetworkSession::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAuthenticated(): bool
    {
        return $this->status === self::STATUS_AUTHENTICATED && ! $this->isExpired();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }

    public function markExpiredIfNeeded(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if (! $this->isExpired()) {
            return false;
        }

        $this->forceFill(['status' => self::STATUS_EXPIRED])->save();

        return true;
    }
}
