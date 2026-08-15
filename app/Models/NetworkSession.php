<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkSession extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'customer_device_id',
        'access_grant_id',
        'network_station_id',
        'network_device_id',
        'network_ssid_id',
        'session_id',
        'mac_address',
        'ip_address',
        'started_at',
        'ended_at',
        'upload_bytes',
        'download_bytes',
        'last_activity_at',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerDevice(): BelongsTo
    {
        return $this->belongsTo(CustomerDevice::class);
    }

    public function accessGrant(): BelongsTo
    {
        return $this->belongsTo(AccessGrant::class);
    }

    public function networkStation(): BelongsTo
    {
        return $this->belongsTo(NetworkStation::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function networkSsid(): BelongsTo
    {
        return $this->belongsTo(NetworkSsid::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(NetworkUsage::class);
    }
}
