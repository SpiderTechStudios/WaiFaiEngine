<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OmadaClientSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_mac' => $this->client_mac,
            'client_ip' => $this->client_ip,
            'ap_mac' => $this->ap_mac,
            'ssid_name' => $this->ssid_name,
            'site_id' => $this->site_id,
            'package_id' => $this->package_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'access_grant_id' => $this->access_grant_id,
            'authorization_status' => $this->authorization_status,
            'authorized_at' => $this->authorized_at,
            'expires_at' => $this->expires_at,
            'disconnected_at' => $this->disconnected_at,
            'controller_reference' => $this->controller_reference,
            'last_sync_status' => $this->last_sync_status,
            'created_at' => $this->created_at,
        ];
    }
}
