<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $online = $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(10));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'vendor' => $this->vendor,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'mac_address' => $this->mac_address,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
            'online' => $online,
            'last_seen_at' => $this->last_seen_at,
            'branch' => $this->whenLoaded('networkStation', fn () => [
                'id' => $this->networkStation?->location_id,
                'name' => $this->networkStation?->location?->name,
                'station_id' => $this->networkStation?->id,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
