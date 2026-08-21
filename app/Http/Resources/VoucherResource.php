<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'expires_at' => $this->expires_at,
            'note' => $this->note,
            'revoked_at' => $this->revoked_at,
            'router' => $this->whenLoaded('router', fn () => [
                'id' => $this->router?->id,
                'name' => $this->router?->name,
                'gateway_type' => $this->router?->gateway_type,
            ]),
            'package' => $this->whenLoaded('internetPlan', fn () => [
                'id' => $this->internetPlan?->id,
                'name' => $this->internetPlan?->name,
                'price' => $this->internetPlan?->price,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
