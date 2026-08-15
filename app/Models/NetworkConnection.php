<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkConnection extends Model
{
    protected $fillable = [
        'company_id',
        'network_station_id',
        'provider',
        'name',
        'credentials',
        'status',
        'last_sync_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_sync_at' => 'datetime',
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

    public function connectionDevices(): HasMany
    {
        return $this->hasMany(NetworkConnectionDevice::class);
    }
}
