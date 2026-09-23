<?php

namespace App\Payments\Drivers;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Payments\PaymentProviderDriver;
use App\Payments\ProviderChargeResult;
use App\Payments\ProviderVerificationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PalmPesa (Tanzania) mobile-money collections.
 *
 * @see https://documentation.palmpesa.co.tz/#getting-started
 */
class PalmPesaPaymentProvider implements PaymentProviderDriver
{
    public function __construct(private PaymentProvider $provider) {}

    public function slug(): string
    {
        return PaymentProvider::SLUG_PALMPESA;
    }

    public function initiateCollection(PlatformPayment $payment): ProviderChargeResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'PALMPESA-STUB-'.strtoupper(Str::random(10)),
                message: 'PalmPesa mobile-money charge simulated (missing credentials / auto-paid mode)',
                raw: ['mode' => 'simulated'],
            );
        }

        $token = $this->apiToken();
        $baseUrl = $this->baseUrl();
        $body = [
            'name' => $this->resolveCustomerName($payment),
            'email' => $this->resolveCustomerEmail($payment),
            'phone' => $this->normalizePhone((string) $payment->phone),
            'amount' => (int) round((float) $payment->amount),
            'transaction_id' => $payment->reference,
            'address' => (string) data_get(
                $payment->metadata,
                'customer_address',
                $this->provider->setting('default_address', 'Dar es Salaam')
            ),
            'postcode' => (string) data_get(
                $payment->metadata,
                'customer_postcode',
                $this->provider->setting('default_postcode', '11111')
            ),
            'callback_url' => (string) ($this->provider->setting('callback_url')
                ?: config('services.palmpesa.callback_url')
                ?: rtrim((string) config('app.url'), '/').'/api/v1/webhooks/payments/'.$this->provider->slug),
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post($baseUrl.'/api/palmpesa/initiate', $body);

        if (! $response->successful()) {
            $providerMessage = $this->extractProviderError($response);

            Log::warning('PalmPesa initiate failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'request' => array_merge($body, ['phone' => $body['phone']]),
                'platform_payment_id' => $payment->id,
                'reference' => $payment->reference,
            ]);

            throw ValidationException::withMessages([
                'payment' => [
                    'Unable to initiate PalmPesa payment: '.$providerMessage,
                ],
            ]);
        }

        $data = $response->json() ?? [];
        $orderId = (string) ($data['order_id'] ?? '');

        if ($orderId === '') {
            throw ValidationException::withMessages([
                'payment' => ['PalmPesa did not return an order_id.'],
            ]);
        }

        return new ProviderChargeResult(
            accepted: true,
            providerReference: $orderId,
            message: (string) ($data['message'] ?? 'PalmPesa mobile-money payment initiated'),
            raw: is_array($data) ? $data : [],
        );
    }

    public function validateWebhook(array $headers, array $payload, ?string $rawBody = null): bool
    {
        $secret = (string) $this->provider->credential('webhook_secret', '');
        if ($secret === '') {
            $secret = (string) config('platform.payment_webhook_secret', '');
        }

        // PalmPesa docs do not define a signature header; optional shared secret if configured.
        if ($secret === '') {
            return true;
        }

        $provided = $this->headerValue($headers, 'x-platform-payment-secret')
            ?: $this->headerValue($headers, 'X-Platform-Payment-Secret')
            ?: $this->headerValue($headers, 'Authorization');

        if (str_starts_with(strtolower($provided), 'bearer ')) {
            $provided = trim(substr($provided, 7));
        }

        return $provided !== '' && hash_equals($secret, $provided);
    }

    public function parseWebhook(array $payload): ProviderVerificationResult
    {
        $data = $payload;
        if (isset($payload['data'][0]) && is_array($payload['data'][0])) {
            $data = array_merge($payload, $payload['data'][0]);
        }

        $status = strtolower((string) (
            $data['payment_status']
            ?? $payload['payment_status']
            ?? $data['status']
            ?? $payload['status']
            ?? $data['result']
            ?? $payload['result']
            ?? 'pending'
        ));

        // PalmPesa reports the terminal state in payment_status; only fall back to
        // resultcode/result when no explicit payment_status is present.
        if ($status === '' || $status === 'pending') {
            $resultCode = (string) ($data['resultcode'] ?? $payload['resultcode'] ?? '');
            $result = strtolower((string) ($data['result'] ?? $payload['result'] ?? ''));
            if ($result !== '' && ! in_array($result, ['pending', 'processing'], true)) {
                $status = $result;
            } elseif ($resultCode !== '' && $resultCode !== '000') {
                $status = 'failed';
            }
        }

        $amount = $data['amount'] ?? $payload['amount'] ?? null;

        return new ProviderVerificationResult(
            status: $status,
            providerReference: isset($data['order_id'])
                ? (string) $data['order_id']
                : (isset($payload['order_id']) ? (string) $payload['order_id'] : null),
            amount: $amount !== null ? (float) $amount : null,
            currency: isset($data['currency'])
                ? (string) $data['currency']
                : (isset($payload['currency']) ? (string) $payload['currency'] : null),
            txRef: (string) (
                $data['transaction_id']
                ?? $payload['transaction_id']
                ?? $data['referenceid']
                ?? $payload['referenceid']
                ?? $data['reference_id']
                ?? $payload['reference_id']
                ?? $data['order_id']
                ?? $payload['order_id']
                ?? $payload['reference']
                ?? ''
            ),
            raw: $payload,
        );
    }

    public function verifyTransaction(PlatformPayment $payment): ProviderVerificationResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderVerificationResult(
                status: 'pending',
                txRef: $payment->reference,
                amount: (float) $payment->amount,
                currency: $payment->currency,
                providerReference: $payment->external_reference,
                raw: ['mode' => 'unverified_no_credentials'],
            );
        }

        $orderId = (string) ($payment->external_reference ?: '');
        if ($orderId === '') {
            return new ProviderVerificationResult(
                status: 'pending',
                txRef: $payment->reference,
                amount: (float) $payment->amount,
                currency: $payment->currency,
                raw: ['mode' => 'missing_order_id'],
            );
        }

        // Keep this short: the provider requires webhooks to answer within 10s and
        // verification runs inline before acknowledging the callback.
        $response = Http::withToken($this->apiToken())
            ->acceptJson()
            ->timeout(max(2, (int) config('services.palmpesa.status_timeout', 8)))
            ->post($this->baseUrl().'/api/order-status', [
                'order_id' => $orderId,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify PalmPesa order status.'],
            ]);
        }

        $payload = $response->json() ?? [];
        $row = is_array($payload['data'][0] ?? null) ? $payload['data'][0] : $payload;
        $status = strtolower((string) ($row['payment_status'] ?? $payload['result'] ?? 'pending'));

        // Normalize SUCCESS from envelope when nested status missing.
        if ($status === 'success' && isset($row['payment_status'])) {
            $status = strtolower((string) $row['payment_status']);
        }

        return new ProviderVerificationResult(
            status: $status,
            providerReference: isset($row['order_id']) ? (string) $row['order_id'] : $orderId,
            amount: isset($row['amount']) ? (float) $row['amount'] : (float) $payment->amount,
            currency: isset($row['currency']) ? (string) $row['currency'] : $payment->currency,
            txRef: $payment->reference,
            raw: is_array($payload) ? $payload : [],
        );
    }

    public function initiatePayout(array $payout): ProviderChargeResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'PALMPESA-PAYOUT-STUB-'.strtoupper(Str::random(8)),
                message: 'PalmPesa payout simulated',
                raw: ['mode' => 'simulated'],
            );
        }

        throw ValidationException::withMessages([
            'payout' => ['PalmPesa collection driver does not support payouts yet. Use send-to-card via a dedicated payout integration.'],
        ]);
    }

    private function shouldSimulate(): bool
    {
        return config('platform.payment_auto_paid') || $this->apiToken() === '';
    }

    private function apiToken(): string
    {
        return (string) config('services.palmpesa.api_token', '');
    }

    private function userId(): string
    {
        return (string) config('services.palmpesa.user_id', '');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.palmpesa.base_url', 'https://palmpesa.drmlelwa.co.tz'), '/');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '255') && strlen($digits) >= 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return $digits;
        }

        if (strlen($digits) === 9) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function resolveCustomerName(PlatformPayment $payment): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) data_get($payment->metadata, 'customer_name', '')));
        $words = $name === '' ? [] : preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        // PalmPesa requires at least two words; single/empty names fall back.
        if (is_array($words) && count($words) >= 2) {
            return implode(' ', $words);
        }

        return 'WiFi Customer';
    }

    private function resolveCustomerEmail(PlatformPayment $payment): string
    {
        $email = trim((string) data_get($payment->metadata, 'customer_email', ''));

        // PalmPesa rejects invalid / non-routable domains (e.g. *.local).
        if (
            $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! str_ends_with(strtolower($email), '.local')
        ) {
            return $email;
        }

        $phone = $this->normalizePhone((string) $payment->phone) ?: 'guest';
        $host = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com'));

        if (
            in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.local')
            || ! str_contains($host, '.')
        ) {
            $host = 'example.com';
        }

        return $phone.'@guest.'.$host;
    }

    private function extractProviderError(Response $response): string
    {
        $json = $response->json();
        if (! is_array($json)) {
            $body = trim((string) $response->body());

            return $body !== '' ? $body : 'HTTP '.$response->status();
        }

        foreach (['message', 'respMsg', 'error', 'error_message'] as $key) {
            if (filled($json[$key] ?? null)) {
                return (string) $json[$key];
            }
        }

        if (isset($json['errors']) && is_array($json['errors'])) {
            $flat = collect($json['errors'])->flatten()->filter()->implode('; ');
            if ($flat !== '') {
                return $flat;
            }
        }

        return 'HTTP '.$response->status().': '.json_encode($json);
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
}
