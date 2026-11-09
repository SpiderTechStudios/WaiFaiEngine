<?php

namespace App\Http\Resources;

use App\Support\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_method_id' => $this->payment_method_id,
            'provider' => PaymentProvider::key($this->payment_method, $metadata['network'] ?? null),
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'provider_transaction_id' => $this->external_reference,
            'failure_reason' => $metadata['failure_reason'] ?? $metadata['provider_message'] ?? null,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'package' => $this->whenLoaded('internetPlan', fn () => [
                'id' => $this->internetPlan?->id,
                'name' => $this->internetPlan?->name,
            ]),
            'branch' => $this->whenLoaded('networkStation', fn () => $this->networkStation ? [
                'id' => $this->networkStation->id,
                'name' => $this->networkStation->name,
            ] : null),
            'company' => $this->whenLoaded('company', fn () => (new CompanyResource($this->company))->resolve()),
            'created_at' => $this->created_at,
        ];
    }
}
