<?php

namespace App\Payments;

use App\Models\PlatformPayment;

final class ProviderChargeResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}
}

final class ProviderVerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerReference = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $txRef = null,
        public readonly array $raw = [],
    ) {}

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['successful', 'success', 'paid', 'completed'], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'failure', 'cancelled', 'canceled'], true);
    }
}

interface PaymentProviderDriver
{
    public function slug(): string;

    /**
     * Initiate a collection (USSD / STK / charge).
     */
    public function initiateCollection(PlatformPayment $payment): ProviderChargeResult;

    /**
     * Validate an inbound webhook request for this provider.
     *
     * @param  array<string, string|array|null>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function validateWebhook(array $headers, array $payload, ?string $rawBody = null): bool;

    /**
     * Parse webhook payload into a normalized verification result.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): ProviderVerificationResult;

    /**
     * Optionally re-verify with the provider API using the internal tx_ref / provider id.
     */
    public function verifyTransaction(PlatformPayment $payment): ProviderVerificationResult;

    /**
     * Initiate a payout/disbursement (future).
     *
     * @param  array<string, mixed>  $payout
     */
    public function initiatePayout(array $payout): ProviderChargeResult;
}
