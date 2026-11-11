<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmadaClientSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'company_id',
        'omada_integration_id',
        'network_device_id',
        'site_id',
        'client_mac',
        'client_ip',
        'ap_mac',
        'ssid_name',
        'package_id',
        'payment_transaction_id',
        'access_grant_id',
        'authorization_status',
        'authorized_at',
        'expires_at',
        'disconnected_at',
        'controller_reference',
        'last_sync_status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(OmadaIntegration::class, 'omada_integration_id');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
