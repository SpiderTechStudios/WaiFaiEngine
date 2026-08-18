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
            'expires_at' => $this->expires_at,
            'redeemed_at' => $this->redeemed_at,
            'internet_plan_id' => $this->internet_plan_id,
            'created_at' => $this->created_at,
        ];
    }
}
