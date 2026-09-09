<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DeviceCatalogService
{
    public function __construct(private CatalogFileService $files) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): Device
    {
        $payload = $this->attributes($data);
        $payload['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        $payload['image'] = $image ? $this->files->store($image, 'devices') : null;

        return Device::query()->create($payload)->load(['brand', 'category']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Device $device, array $data, ?UploadedFile $image = null): Device
    {
        $payload = $this->attributes($data, partial: true);

        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            $payload['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name'] ?? $device->name, $device->name, $device->id);
        }

        $payload['image'] = $this->files->replace(
            $device->image,
            $image,
            'devices',
            (bool) ($data['remove_image'] ?? false),
        );

        $device->fill($payload)->save();

        return $device->fresh()->load(['brand', 'category']);
    }

    public function delete(Device $device): void
    {
        $this->files->delete($device->image);
        $device->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, bool $partial = false): array
    {
        $fields = [
            'name',
            'model',
            'sku',
            'description',
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

        $payload = [];

        foreach ($fields as $field) {
            if (! $partial || array_key_exists($field, $data)) {
                if (array_key_exists($field, $data) || ! $partial) {
                    $payload[$field] = $data[$field] ?? null;
                }
            }
        }

        if (! $partial) {
            $payload['stock_quantity'] = $data['stock_quantity'] ?? 0;
            $payload['reorder_level'] = $data['reorder_level'] ?? 0;
            $payload['is_active'] = $data['is_active'] ?? true;
            $payload['is_featured'] = $data['is_featured'] ?? false;
            $payload['is_configurable'] = $data['is_configurable'] ?? false;
        }

        unset($payload['slug'], $payload['image']);

        return $payload;
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        if ($base === '') {
            $base = 'device';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Device::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
