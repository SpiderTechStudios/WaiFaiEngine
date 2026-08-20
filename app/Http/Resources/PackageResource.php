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
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
