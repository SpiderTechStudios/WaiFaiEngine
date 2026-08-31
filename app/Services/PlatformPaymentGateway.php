<?php

namespace App\Services;

use App\Models\PlatformPayment;

/**
 * Stub mobile-money / USSD push gateway.
 * Replace with a real provider client when wiring production STK/USSD.
 */
class PlatformPaymentGateway
{
    /**
     * @return array{accepted: bool, provider_reference: string|null, message: string}
     */
    public function pushUssd(PlatformPayment $payment): array
    {
        $reference = 'USSD-'.strtoupper(str()->random(12));

        $metadata = $payment->metadata ?? [];
        $metadata['ussd_push'] = [
            'accepted' => true,
            'provider_reference' => $reference,
            'pushed_at' => now()->toIso8601String(),
            'phone' => $payment->phone,
        ];

        $payment->forceFill([
            'external_reference' => $reference,
            'metadata' => $metadata,
        ])->save();

        return [
            'accepted' => true,
            'provider_reference' => $reference,
            'message' => 'USSD push initiated',
        ];
    }
}
