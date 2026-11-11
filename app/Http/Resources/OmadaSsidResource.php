<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OmadaSsidResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wlan_id' => $this->wlan_id,
            'ssid_name' => $this->ssid_name,
            'status' => $this->status,
            'portal_enabled' => (bool) $this->portal_enabled,
            'selected_for_waifai' => (bool) $this->selected_for_waifai,
            'site_id' => $this->site_id,
            'updated_at' => $this->updated_at,
        ];
    }
}
