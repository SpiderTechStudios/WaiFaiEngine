<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'network' => $this->network,
            'payment_method_id' => $this->payment_method_id,
            'payment_method_slug' => $this->paymentMethod?->key,
            'payment_method_name' => $this->paymentMethod?->name,
            'payment_method_logo_url' => $this->paymentMethod?->logo_url,
            'payment_provider_slug' => $this->payment_provider_slug,
            'payment_provider' => $this->whenLoaded('paymentProvider', function () {
                if ($this->paymentProvider) {
                    return [
                        'id' => $this->paymentProvider->id,
                        'name' => $this->paymentProvider->name,
                        'slug' => $this->paymentProvider->slug,
                    ];
                }

                return $this->payment_provider_slug ? [
                    'id' => $this->payment_provider_id,
                    'name' => null,
                    'slug' => $this->payment_provider_slug,
                ] : null;
            }),
            'payout_account' => $this->whenLoaded(
                'payoutAccount',
                fn () => $this->payoutAccount ? (new PayoutAccountResource($this->payoutAccount))->resolve() : null,
            ),
            'destination_phone' => $this->destination_phone,
            'destination_name' => $this->destination_name,
            'amount' => $this->amount,
            'platform_fee' => $this->platform_fee,
            'provider_fee' => $this->provider_fee,
            'fee_total' => $this->fee_total,
            'net_amount' => $this->net_amount,
            'fee_breakdown' => $this->fee_breakdown,
            'currency' => $this->currency,
            'status' => $this->status,
            'requires_confirmation' => $this->isAwaitingConfirmation(),
            'requires_approval' => $this->isAwaitingApproval(),
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'denied_at' => $this->denied_at,
            'denied_by' => $this->denied_by,
            'denial_reason' => $this->denial_reason,
            'otp_channel' => data_get($this->metadata, 'otp_channel'),
            'otp_expires_at' => $this->otp_expires_at,
            'confirmed_at' => $this->confirmed_at,
            'requested_at' => $this->requested_at,
            'processed_at' => $this->processed_at,
            'failed_at' => $this->failed_at,
            'cancelled_at' => $this->cancelled_at,
            'failure_reason' => $this->failure_reason,
            'company' => $this->whenLoaded('company', fn () => (new CompanyResource($this->company))->resolve()),
            'created_at' => $this->created_at,
        ];
    }
}
