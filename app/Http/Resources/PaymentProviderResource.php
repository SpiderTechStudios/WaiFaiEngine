<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'supports_payments' => $this->supports_payments,
            'supports_payouts' => $this->supports_payouts,
            'is_active' => $this->is_active,
            'is_default_for_payments' => $this->is_default_for_payments,
            'is_default_for_payouts' => $this->is_default_for_payouts,
            'credentials' => $this->publicCredentials(),
            'settings' => $this->settings,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
