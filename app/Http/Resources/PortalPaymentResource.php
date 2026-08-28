<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'package' => $this->whenLoaded('internetPlan', fn () => $this->internetPlan ? [
                'id' => $this->internetPlan->id,
                'name' => $this->internetPlan->name,
                'price' => $this->internetPlan->price,
            ] : null),
        ];
    }
}
