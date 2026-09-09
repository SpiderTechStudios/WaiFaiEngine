<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'model',
        'sku',
        'description',
        'image',
        'device_category_id',
        'brand_id',

        'price',
        'cost_price',
        'stock_quantity',
        'reorder_level',

        'network_type',
        'device_type',
        'operating_system',

        'lan_ports',
        'wan_ports',
        'sfp_ports',
        'sfp_plus_ports',
        'usb_ports',
        'console_ports',

        'ethernet_speed',
        'gigabit',

        'wifi_standard',
        'wifi_speed',
        'wifi_frequency',
        'max_wifi_clients',
        'antenna_count',
        'antenna_type',
        'antenna_gain',

        'sim_support',
        'sim_type',
        'cellular_network',
        'cellular_speed',
        'supported_bands',

        'poe_support',
        'poe_standard',
        'poe_power',

        'routing_support',
        'max_routes',
        'routing_protocols',

        'managed',
        'vlan_support',
        'qos_support',
        'switching_capacity',
        'forwarding_rate',

        'firewall_support',
        'vpn_support',
        'vpn_protocols',
        'security_features',

        'dimensions',
        'weight',
        'mounting',
        'ip_rating',

        'power_input',
        'power_consumption',

        'is_active',
        'is_featured',
        'is_configurable',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',

        'gigabit' => 'boolean',
        'sim_support' => 'boolean',
        'poe_support' => 'boolean',
        'routing_support' => 'boolean',
        'managed' => 'boolean',
        'vlan_support' => 'boolean',
        'qos_support' => 'boolean',
        'firewall_support' => 'boolean',
        'vpn_support' => 'boolean',

        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_configurable' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}