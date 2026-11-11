<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OmadaSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'site_name' => $this->site_name,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'selected' => $this->site_id === $this->integration?->site_id,
            'updated_at' => $this->updated_at,
        ];
    }
}
