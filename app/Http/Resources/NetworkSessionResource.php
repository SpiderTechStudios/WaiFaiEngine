<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'access_grant_id' => $this->access_grant_id,
            'internet_plan_id' => $this->internet_plan_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'mac_address' => $this->mac_address,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'last_activity_at' => $this->last_activity_at,
            'upload_bytes' => $this->upload_bytes,
            'download_bytes' => $this->download_bytes,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'package' => $this->whenLoaded('internetPlan', fn () => $this->internetPlan ? [
                'id' => $this->internetPlan->id,
                'name' => $this->internetPlan->name,
                'badge' => $this->internetPlan->badge,
                'duration' => $this->internetPlan->duration,
                'duration_unit' => $this->internetPlan->duration_unit,
                'price' => $this->internetPlan->price,
                'status' => $this->internetPlan->status,
            ] : null),
            'payment' => $this->whenLoaded('paymentTransaction', fn () => $this->paymentTransaction ? [
                'id' => $this->paymentTransaction->id,
                'reference' => $this->paymentTransaction->reference,
                'amount' => $this->paymentTransaction->amount,
                'currency' => $this->paymentTransaction->currency,
                'payment_method' => $this->paymentTransaction->payment_method,
                'status' => $this->paymentTransaction->status,
                'paid_at' => $this->paymentTransaction->paid_at,
            ] : null),
            'router' => $this->whenLoaded('networkDevice', fn () => [
                'id' => $this->networkDevice?->id,
                'name' => $this->networkDevice?->name,
            ]),
            'access_grant' => $this->whenLoaded('accessGrant', fn () => [
                'id' => $this->accessGrant?->id,
                'status' => $this->accessGrant?->status,
                'source' => $this->accessGrant?->source,
                'starts_at' => $this->accessGrant?->starts_at,
                'expires_at' => $this->accessGrant?->expires_at,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
