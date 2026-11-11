<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmadaAccessPoint extends Model
{
    protected $fillable = [
        'company_id',
        'omada_integration_id',
        'site_id',
        'device_id',
        'device_name',
        'mac_address',
        'model',
        'ip_address',
        'firmware_version',
        'online_status',
        'selected_for_waifai',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selected_for_waifai' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(OmadaIntegration::class, 'omada_integration_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
