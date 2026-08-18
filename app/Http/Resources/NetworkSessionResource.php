<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mac_address' => $this->mac_address,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'last_activity_at' => $this->last_activity_at,
            'upload_bytes' => $this->upload_bytes,
            'download_bytes' => $this->download_bytes,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'router' => $this->whenLoaded('networkDevice', fn () => [
                'id' => $this->networkDevice?->id,
                'name' => $this->networkDevice?->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
