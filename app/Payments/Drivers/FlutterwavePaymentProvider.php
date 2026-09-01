<?php

namespace App\Payments\Drivers;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Payments\PaymentProviderDriver;
use App\Payments\ProviderChargeResult;
use App\Payments\ProviderVerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FlutterwavePaymentProvider implements PaymentProviderDriver
{
    public function __construct(private PaymentProvider $provider) {}

    public function slug(): string
    {
        return PaymentProvider::SLUG_FLUTTERWAVE;
    }

    public function initiateCollection(PlatformPayment $payment): ProviderChargeResult
    {
        $secret = (string) $this->provider->credential('secret_key', '');

        // Local/dev without secrets: simulate USSD acceptance (tests use webhook/markPaid).
        if ($secret === '' || config('platform.payment_auto_paid')) {
            $reference = 'FLW-STUB-'.strtoupper(Str::random(12));

            return new ProviderChargeResult(
                accepted: true,
                providerReference: $reference,
                message: 'Flutterwave charge simulated (no secret key / auto-paid mode)',
                raw: ['mode' => 'simulated'],
            );
        }

        $baseUrl = rtrim((string) $this->provider->credential(
            'api_base_url',
            config('services.flutterwave.base_url', 'https://api.flutterwave.com')
        ), '/');

        $chargeType = (string) $this->provider->setting(
            'mobile_money_charge_type',
            'mobile_money_tanzania'
        );

        $email = (string) data_get($payment->metadata, 'customer_email', 'payments@waifai.local');
        $name = (string) data_get($payment->metadata, 'customer_name', 'WaiFai Customer');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->post($baseUrl.'/v3/charges?type='.urlencode($chargeType), [
                'tx_ref' => $payment->reference,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'email' => $email,
                'phone_number' => $payment->phone,
                'fullname' => $name,
                'redirect_url' => (string) $this->provider->setting(
                    'redirect_url',
                    config('app.url').'/payment/complete'
                ),
                'meta' => [
                    'purpose' => $payment->resolvePurpose(),
                    'payment_id' => $payment->id,
                ],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Unable to initiate Flutterwave payment: '.($response->json('message') ?? $response->body()),
                ],
            ]);
        }

        $data = $response->json('data') ?? [];

        return new ProviderChargeResult(
            accepted: true,
            providerReference: (string) ($data['flw_ref'] ?? $data['id'] ?? ''),
            message: (string) ($response->json('message') ?? 'Charge initiated'),
            raw: is_array($data) ? $data : [],
        );
    }

    public function validateWebhook(array $headers, array $payload, ?string $rawBody = null): bool
    {
        $secret = (string) $this->provider->credential('webhook_secret', '');
        if ($secret === '') {
            $secret = (string) config('platform.payment_webhook_secret', '');
        }

        if ($secret === '') {
            return (bool) config('platform.payment_auto_paid');
        }

        $provided = $this->headerValue($headers, 'verif-hash')
            ?: $this->headerValue($headers, 'Verif-Hash');

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
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $status = strtolower((string) ($data['status'] ?? $payload['status'] ?? ''));

        return new ProviderVerificationResult(
            status: $status,
            providerReference: isset($data['flw_ref']) ? (string) $data['flw_ref'] : (isset($data['id']) ? (string) $data['id'] : null),
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            txRef: isset($data['tx_ref']) ? (string) $data['tx_ref'] : (isset($payload['tx_ref']) ? (string) $payload['tx_ref'] : null),
            raw: $payload,
        );
    }

    public function verifyTransaction(PlatformPayment $payment): ProviderVerificationResult
    {
        $secret = (string) $this->provider->credential('secret_key', '');
        if ($secret === '') {
            return new ProviderVerificationResult(
                status: 'pending',
                txRef: $payment->reference,
                amount: (float) $payment->amount,
                currency: $payment->currency,
                raw: ['mode' => 'unverified_no_secret'],
            );
        }

        $baseUrl = rtrim((string) $this->provider->credential(
            'api_base_url',
            config('services.flutterwave.base_url', 'https://api.flutterwave.com')
        ), '/');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->get($baseUrl.'/v3/transactions/verify_by_reference', [
                'tx_ref' => $payment->reference,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify Flutterwave transaction.'],
            ]);
        }

        $data = $response->json('data') ?? [];

        return new ProviderVerificationResult(
            status: strtolower((string) ($data['status'] ?? 'pending')),
            providerReference: isset($data['flw_ref']) ? (string) $data['flw_ref'] : null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            txRef: isset($data['tx_ref']) ? (string) $data['tx_ref'] : $payment->reference,
            raw: is_array($data) ? $data : [],
        );
    }

    public function initiatePayout(array $payout): ProviderChargeResult
    {
        $secret = (string) $this->provider->credential('secret_key', '');
        if ($secret === '') {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'FLW-PAYOUT-STUB-'.strtoupper(Str::random(10)),
                message: 'Flutterwave payout simulated',
                raw: ['mode' => 'simulated'],
            );
        }

        $baseUrl = rtrim((string) $this->provider->credential(
            'api_base_url',
            config('services.flutterwave.base_url', 'https://api.flutterwave.com')
        ), '/');

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->post($baseUrl.'/v3/transfers', $payout);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payout' => [
                    'Unable to initiate Flutterwave payout: '.($response->json('message') ?? $response->body()),
                ],
            ]);
        }

        $data = $response->json('data') ?? [];

        return new ProviderChargeResult(
            accepted: true,
            providerReference: (string) ($data['id'] ?? $data['reference'] ?? ''),
            message: (string) ($response->json('message') ?? 'Transfer initiated'),
            raw: is_array($data) ? $data : [],
        );
    }
}
