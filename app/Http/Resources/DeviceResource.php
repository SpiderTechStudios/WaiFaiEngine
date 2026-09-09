<?php

namespace App\Http\Resources;

use App\Services\CatalogFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'model' => $this->model,
            'sku' => $this->sku,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => app(CatalogFileService::class)->url($this->image),
            'device_category_id' => $this->device_category_id,
            'brand_id' => $this->brand_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo_url' => app(CatalogFileService::class)->url($this->brand->logo),
            ] : null),
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'stock_quantity' => $this->stock_quantity,
            'reorder_level' => $this->reorder_level,
            'network_type' => $this->network_type,
            'device_type' => $this->device_type,
            'operating_system' => $this->operating_system,
            'lan_ports' => $this->lan_ports,
            'wan_ports' => $this->wan_ports,
            'sfp_ports' => $this->sfp_ports,
            'sfp_plus_ports' => $this->sfp_plus_ports,
            'usb_ports' => $this->usb_ports,
            'console_ports' => $this->console_ports,
            'ethernet_speed' => $this->ethernet_speed,
            'gigabit' => $this->gigabit,
            'wifi_standard' => $this->wifi_standard,
            'wifi_speed' => $this->wifi_speed,
            'wifi_frequency' => $this->wifi_frequency,
            'max_wifi_clients' => $this->max_wifi_clients,
            'antenna_count' => $this->antenna_count,
            'antenna_type' => $this->antenna_type,
            'antenna_gain' => $this->antenna_gain,
            'sim_support' => $this->sim_support,
            'sim_type' => $this->sim_type,
            'cellular_network' => $this->cellular_network,
            'cellular_speed' => $this->cellular_speed,
            'supported_bands' => $this->supported_bands,
            'poe_support' => $this->poe_support,
            'poe_standard' => $this->poe_standard,
            'poe_power' => $this->poe_power,
            'routing_support' => $this->routing_support,
            'max_routes' => $this->max_routes,
            'routing_protocols' => $this->routing_protocols,
            'managed' => $this->managed,
            'vlan_support' => $this->vlan_support,
            'qos_support' => $this->qos_support,
            'switching_capacity' => $this->switching_capacity,
            'forwarding_rate' => $this->forwarding_rate,
            'firewall_support' => $this->firewall_support,
            'vpn_support' => $this->vpn_support,
            'vpn_protocols' => $this->vpn_protocols,
            'security_features' => $this->security_features,
            'dimensions' => $this->dimensions,
            'weight' => $this->weight,
            'mounting' => $this->mounting,
            'ip_rating' => $this->ip_rating,
            'power_input' => $this->power_input,
            'power_consumption' => $this->power_consumption,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_configurable' => $this->is_configurable,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
