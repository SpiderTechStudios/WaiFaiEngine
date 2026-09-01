<?php

namespace App\Payments\Drivers;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Payments\PaymentProviderDriver;
use App\Payments\ProviderChargeResult;
use App\Payments\ProviderVerificationResult;
use Illuminate\Support\Str;

/**
 * Deterministic local/test driver. Never calls an external network.
 */
class StubPaymentProvider implements PaymentProviderDriver
{
    public function __construct(private PaymentProvider $provider) {}

    public function slug(): string
    {
        return PaymentProvider::SLUG_STUB;
    }

    public function initiateCollection(PlatformPayment $payment): ProviderChargeResult
    {
        return new ProviderChargeResult(
            accepted: true,
            providerReference: 'STUB-'.strtoupper(Str::random(12)),
            message: 'Stub USSD push accepted',
            raw: ['mode' => 'stub'],
        );
    }

    public function validateWebhook(array $headers, array $payload, ?string $rawBody = null): bool
    {
        $secret = (string) $this->provider->credential('webhook_secret', config('platform.payment_webhook_secret'));
        if ($secret === '') {
            return true;
        }

        $provided = $this->headerValue($headers, 'x-platform-payment-secret')
            ?: $this->headerValue($headers, 'X-Platform-Payment-Secret');

        return $provided !== '' && hash_equals($secret, $provided);
    }

    /**
     * @param  array<string, string|array|null>  $headers
     */
    private function headerValue(array $headers, string $name): string
    {
        $value = $headers[$name] ?? $headers[strtolower($name)] ?? null;
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return (string) ($value ?? '');
    }

    public function parseWebhook(array $payload): ProviderVerificationResult
    {
        return new ProviderVerificationResult(
            status: strtolower((string) ($payload['status'] ?? 'pending')),
            providerReference: isset($payload['provider_reference']) ? (string) $payload['provider_reference'] : null,
            amount: isset($payload['amount']) ? (float) $payload['amount'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            txRef: (string) ($payload['reference'] ?? $payload['tx_ref'] ?? $payload['transaction_reference'] ?? ''),
            raw: $payload,
        );
    }

    public function verifyTransaction(PlatformPayment $payment): ProviderVerificationResult
    {
        return new ProviderVerificationResult(
            status: $payment->status === PlatformPayment::STATUS_PAID ? 'successful' : $payment->status,
            providerReference: $payment->external_reference,
            amount: (float) $payment->amount,
            currency: $payment->currency,
            txRef: $payment->reference,
            raw: ['mode' => 'stub'],
        );
    }

    public function initiatePayout(array $payout): ProviderChargeResult
    {
        return new ProviderChargeResult(
            accepted: true,
            providerReference: 'STUB-PAYOUT-'.strtoupper(Str::random(10)),
            message: 'Stub payout accepted',
            raw: ['mode' => 'stub'],
        );
    }
}
