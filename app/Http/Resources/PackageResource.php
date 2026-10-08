<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'badge' => $this->badge,
            'description' => $this->description,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'price' => $this->price,
            'speed_download_mbps' => $this->speed_download_mbps,
            'speed_upload_mbps' => $this->speed_upload_mbps,
            'data_cap_mb' => $this->data_cap_mb,
            'devices_allowed' => $this->devices_allowed,
            'status' => $this->status,
            'sort_order' => (int) $this->sort_order,
            'visible_on_portal' => (bool) $this->visible_on_portal,
            // Sales metrics, only present when the caller asked for them.
            'sold' => $this->when($this->hasMetric('sold'), fn () => (int) $this->sold),
            'revenue' => $this->when($this->hasMetric('revenue'), fn () => (float) $this->revenue),
            'last_sold_at' => $this->when($this->hasMetric('last_sold_at'), fn () => $this->last_sold_at),
            'created_at' => $this->created_at,
        ];
    }

    private function hasMetric(string $key): bool
    {
        return array_key_exists($key, $this->resource->getAttributes());
    }
}
