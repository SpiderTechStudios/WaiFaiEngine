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
        $price = $this->relationLoaded('prices')
            ? $this->prices->firstWhere('status', 'active') ?? $this->prices->sortByDesc('id')->first()
            : $this->prices()->where('status', 'active')->latest('id')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'data_limit' => $this->data_limit,
            'data_limit_unit' => $this->data_limit_unit,
            'download_speed' => $this->download_speed,
            'upload_speed' => $this->upload_speed,
            'speed_unit' => $this->speed_unit,
            'max_devices' => $this->max_devices,
            'price' => $price?->amount,
            'currency' => $price?->currency,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
