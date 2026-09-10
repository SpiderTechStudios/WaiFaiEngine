<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $charge = data_get($this->metadata, 'provider_charge.raw', []);

        return [
            'payment_id' => $this->id,
            'reference' => $this->reference,
            'purpose' => $this->resolvePurpose(),
            'provider' => $this->provider_slug,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'line_items' => $this->line_items,
            'paid_at' => $this->paid_at,
            'initiated_at' => $this->initiated_at,
            'expires_at' => null,
            'checkout_url' => data_get($charge, 'checkoutUrl'),
            'payment_instructions' => array_filter([
                'virtual_account' => data_get($charge, 'virtual_account'),
                'message' => data_get($this->metadata, 'provider_charge.message'),
            ], fn ($value) => filled($value)),
        ];
    }
}
