<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'fulfillment_method' => $this->fulfillment_method,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'device_id' => $item->device_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values()),
            'payment' => $this->whenLoaded('payments', function () {
                $payment = $this->payments->sortByDesc('id')->first();

                return $payment ? (new PlatformPaymentResource($payment))->resolve() : null;
            }),
            'created_at' => $this->created_at,
        ];
    }
}
