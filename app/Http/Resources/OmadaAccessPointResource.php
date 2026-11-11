<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OmadaAccessPointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'mac_address' => $this->mac_address,
            'model' => $this->model,
            'ip_address' => $this->ip_address,
            'firmware_version' => $this->firmware_version,
            'online_status' => $this->online_status,
            'selected_for_waifai' => (bool) $this->selected_for_waifai,
            'site_id' => $this->site_id,
            'updated_at' => $this->updated_at,
        ];
    }
}
