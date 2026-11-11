<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmadaSsid extends Model
{
    protected $fillable = [
        'company_id',
        'omada_integration_id',
        'site_id',
        'wlan_id',
        'ssid_name',
        'status',
        'portal_enabled',
        'selected_for_waifai',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal_enabled' => 'boolean',
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
