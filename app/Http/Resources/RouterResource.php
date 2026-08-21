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
            'gateway_type' => $this->gateway_type,
            'name' => $this->name,
            'lan_ip' => $this->lan_ip,
            'api_host' => $this->api_host,
            'api_port' => $this->api_port,
            'api_username' => $this->api_username,
            'api_password_set' => filled($this->api_password),
            'gateway_id' => $this->gateway_id,
            'serial_number' => $this->serial_number,
            'wifidog_port' => $this->wifidog_port,
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
