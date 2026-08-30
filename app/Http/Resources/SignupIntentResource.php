<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignupIntentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'intent_id' => $this->id,
            'status' => $this->status,
            'setup_type' => $this->setup_type,
            'expires_at' => $this->expires_at,
            'pricing' => [
                'installation_fee' => $this->installation_fee,
                'subscription_fee' => $this->subscription_fee,
                'total_amount' => $this->total_amount,
                'currency' => $this->currency,
            ],
            'portal_subdomain' => $this->portal_subdomain,
            'business_name' => $this->business_name,
            'email' => $this->email,
        ];
    }
}
