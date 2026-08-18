<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'package' => $this->whenLoaded('internetPlan', fn () => [
                'id' => $this->internetPlan?->id,
                'name' => $this->internetPlan?->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
