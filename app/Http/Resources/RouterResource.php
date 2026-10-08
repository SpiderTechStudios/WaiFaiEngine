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
        return [
            'id' => $this->id,
            'gateway_type' => $this->gateway_type,
            'name' => $this->name,
            'model' => $this->model,
            'lan_ip' => $this->lan_ip,
            'api_host' => $this->api_host,
            'api_port' => $this->api_port,
            'api_username' => $this->api_username,
            'api_password_set' => filled($this->api_password),
            'gateway_id' => $this->gateway_id,
            'serial_number' => $this->serial_number,
            'firmware' => $this->firmware,
            'mac_address' => $this->mac_address,
            'wifidog_port' => $this->wifidog_port,
            'status' => $this->status,
            'online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at,
            'branch' => $this->whenLoaded('networkStation', fn () => [
                'id' => $this->networkStation?->location_id,
                'name' => $this->networkStation?->location?->name,
                'station_id' => $this->networkStation?->id,
            ]),
            // Live metrics, only present when the caller asked for them.
            'clients_now' => $this->when($this->hasMetric('clients_now'), fn () => (int) $this->clients_now),
            'revenue_today' => $this->when($this->hasMetric('revenue_today'), fn () => (float) $this->revenue_today),
            'payments_today' => $this->when($this->hasMetric('payments_today'), fn () => (int) $this->payments_today),
            'uptime_seconds' => $this->when($this->hasMetric('uptime_seconds'), fn () => (int) $this->uptime_seconds),
            'revenue' => $this->when($this->hasMetric('revenue'), fn () => $this->revenue),
            'created_at' => $this->created_at,
        ];
    }

    private function hasMetric(string $key): bool
    {
        return array_key_exists($key, $this->resource->getAttributes());
    }
}
