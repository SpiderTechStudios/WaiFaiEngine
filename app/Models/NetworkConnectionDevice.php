<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkConnectionDevice extends Model
{
    protected $fillable = [
        'network_connection_id',
        'network_device_id',
        'external_id',
        'external_serial_number',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function networkConnection(): BelongsTo
    {
        return $this->belongsTo(NetworkConnection::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }
}
