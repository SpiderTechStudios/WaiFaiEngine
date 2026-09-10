<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->resource;

        $latestPayment = $enrollment->payments()
            ->latest('id')
            ->first();

        $payload = [
            'enrollment_reference' => $enrollment->reference,
            'enrollment_status' => $enrollment->status,
            'payment_status' => $latestPayment?->status,
            'amount' => $enrollment->total_amount,
            'currency' => $enrollment->currency,
            'expires_at' => optional($enrollment->expires_at)?->toIso8601String(),
            'remaining_attempts' => $enrollment->remainingAttempts(),
            'payment_phone' => $enrollment->payment_phone,
        ];

        if ($enrollment->isCompleted()) {
            $payload['account_created'] = true;
            $payload['next_action'] = 'login';
            $payload['redirect_to'] = '/login';
        }

        if ($latestPayment) {
            $charge = data_get($latestPayment->metadata, 'provider_charge.raw', []);
            $payload['payment'] = [
                'status' => $latestPayment->status,
                'amount' => $latestPayment->amount,
                'currency' => $latestPayment->currency,
                'reference' => $latestPayment->reference,
                'purpose' => $latestPayment->resolvePurpose(),
                'provider' => $latestPayment->provider_slug,
                'checkout_url' => data_get($charge, 'checkoutUrl'),
                'payment_instructions' => array_filter([
                    'virtual_account' => data_get($charge, 'virtual_account'),
                    'message' => data_get($latestPayment->metadata, 'provider_charge.message'),
                ], fn ($value) => filled($value)),
            ];
        }

        return $payload;
    }
}
