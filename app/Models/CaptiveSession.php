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
        'mikrotik_username',
        'mikrotik_password',
        'mikrotik_user_id',
        'mikrotik_grant_id',
        'mikrotik_provisioned_at',
        'router_active_id',
        'router_authorized_at',
        'router_last_seen_at',
    ];

    protected $hidden = [
        'mikrotik_password',
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
            'mikrotik_grant_id' => 'integer',
            'mikrotik_provisioned_at' => 'datetime',
            'router_authorized_at' => 'datetime',
            'router_last_seen_at' => 'datetime',
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

    /**
     * The access grant is the source of truth for how long access lasts. The
     * session's own expires_at is only a cached copy and may be shorter (for
     * example a pending-session TTL or a legacy row), so it must never end a
     * customer's access before the grant does.
     */
    public function currentGrant(): ?AccessGrant
    {
        if (! $this->access_grant_id) {
            return null;
        }

        return $this->relationLoaded('accessGrant')
            ? $this->accessGrant
            : $this->accessGrant()->first();
    }

    public function syncExpiryToCurrentGrant(): void
    {
        $grant = $this->currentGrant();

        if (! $grant || ! $grant->isUsable()) {
            return;
        }

        if ($this->expires_at === null && $grant->expires_at === null) {
            return;
        }

        if ($this->expires_at !== null && $grant->expires_at !== null && $this->expires_at->equalTo($grant->expires_at)) {
            return;
        }

        $this->forceFill(['expires_at' => $grant->expires_at])->save();
    }

    public function markExpiredIfNeeded(): bool
    {
        $grant = $this->currentGrant();

        if ($grant && $grant->isUsable()) {
            $this->syncExpiryToCurrentGrant();

            return false;
        }

        if ($grant) {
            if ($this->status !== self::STATUS_EXPIRED) {
                $this->forceFill(['status' => self::STATUS_EXPIRED])->save();
            }

            return true;
        }

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
