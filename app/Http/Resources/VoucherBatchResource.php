<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'currency' => $this->currency,
            'status' => $this->status,
            'package' => $this->whenLoaded('internetPlan', fn () => [
                'id' => $this->internetPlan?->id,
                'name' => $this->internetPlan?->name,
            ]),
            'vouchers' => VoucherResource::collection($this->whenLoaded('vouchers')),
            'created_at' => $this->created_at,
        ];
    }
}
