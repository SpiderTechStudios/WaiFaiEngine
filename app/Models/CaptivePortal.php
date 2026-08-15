<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptivePortal extends Model
{
    protected $fillable = [
        'company_id',
        'network_station_id',
        'network_ssid_id',
        'name',
        'headline',
        'welcome_message',
        'logo_path',
        'primary_color',
        'background_color',
        'terms',
        'success_message',
        'language',
        'show_plans',
        'show_vouchers',
        'show_mobile_money',
        'status',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_plans' => 'boolean',
            'show_vouchers' => 'boolean',
            'show_mobile_money' => 'boolean',
            'settings' => 'array',
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

    public function networkSsid(): BelongsTo
    {
        return $this->belongsTo(NetworkSsid::class);
    }
}
