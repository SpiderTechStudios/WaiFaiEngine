<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Validation\Rule;

class DeviceCatalogRules
{
    /**
     * @return array<string, mixed>
     */
    public static function fields(bool $creating, ?int $deviceId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('devices', 'slug')->ignore($deviceId)],
            'model' => ['nullable', 'string', 'max:255'],
            'sku' => [$required, 'string', 'max:255', Rule::unique('devices', 'sku')->ignore($deviceId)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'device_category_id' => [$required, 'integer', 'exists:device_categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'network_type' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'lan_ports' => ['nullable', 'integer', 'min:0'],
            'wan_ports' => ['nullable', 'integer', 'min:0'],
            'sfp_ports' => ['nullable', 'integer', 'min:0'],
            'sfp_plus_ports' => ['nullable', 'integer', 'min:0'],
            'usb_ports' => ['nullable', 'integer', 'min:0'],
            'console_ports' => ['nullable', 'integer', 'min:0'],
            'ethernet_speed' => ['nullable', 'string', 'max:255'],
            'gigabit' => ['nullable', 'boolean'],
            'wifi_standard' => ['nullable', 'string', 'max:255'],
            'wifi_speed' => ['nullable', 'string', 'max:255'],
            'wifi_frequency' => ['nullable', 'string', 'max:255'],
            'max_wifi_clients' => ['nullable', 'integer', 'min:0'],
            'antenna_count' => ['nullable', 'integer', 'min:0'],
            'antenna_type' => ['nullable', 'string', 'max:255'],
            'antenna_gain' => ['nullable', 'string', 'max:255'],
            'sim_support' => ['nullable', 'boolean'],
            'sim_type' => ['nullable', 'string', 'max:255'],
            'cellular_network' => ['nullable', 'string', 'max:255'],
            'cellular_speed' => ['nullable', 'string', 'max:255'],
            'supported_bands' => ['nullable', 'string', 'max:255'],
            'poe_support' => ['nullable', 'boolean'],
            'poe_standard' => ['nullable', 'string', 'max:255'],
            'poe_power' => ['nullable', 'string', 'max:255'],
            'routing_support' => ['nullable', 'boolean'],
            'max_routes' => ['nullable', 'integer', 'min:0'],
            'routing_protocols' => ['nullable', 'string', 'max:255'],
            'managed' => ['nullable', 'boolean'],
            'vlan_support' => ['nullable', 'boolean'],
            'qos_support' => ['nullable', 'boolean'],
            'switching_capacity' => ['nullable', 'string', 'max:255'],
            'forwarding_rate' => ['nullable', 'string', 'max:255'],
            'firewall_support' => ['nullable', 'boolean'],
            'vpn_support' => ['nullable', 'boolean'],
            'vpn_protocols' => ['nullable', 'string', 'max:255'],
            'security_features' => ['nullable', 'string', 'max:255'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'string', 'max:255'],
            'mounting' => ['nullable', 'string', 'max:255'],
            'ip_rating' => ['nullable', 'string', 'max:255'],
            'power_input' => ['nullable', 'string', 'max:255'],
            'power_consumption' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_configurable' => ['nullable', 'boolean'],
        ];
    }
}
