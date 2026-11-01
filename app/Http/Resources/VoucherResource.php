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
            'is_used' => (int) $this->uses_count > 0,
            'remaining_uses' => max(0, (int) $this->max_uses - (int) $this->uses_count),
            'fully_used' => (int) $this->uses_count >= (int) $this->max_uses,
            'last_used_at' => $this->whenLoaded('latestGrant', fn () => $this->latestGrant?->created_at),
            'used_by' => $this->whenLoaded('latestGrant', function () {
                $customer = $this->latestGrant?->customer;

                return $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ] : null;
            }),
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
                'duration' => $this->internetPlan?->duration,
                'duration_unit' => $this->internetPlan?->duration_unit,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
