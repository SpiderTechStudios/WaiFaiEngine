<?php

namespace App\Payments\Drivers;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Payments\PaymentProviderDriver;
use App\Payments\ProviderChargeResult;
use App\Payments\ProviderVerificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AfriqorePay (Tanzania) collections and disbursements.
 *
 * @see https://pay.afriqorepay.com/docs
 *
 * - OAuth: Basic app_id:passkey -> POST /oauth/token (client_credentials)
 * - Collections: POST /collections, GET /collections/{transaction_id}
 * - Disbursements: POST /disbursements, POST /disbursements/preview
 * - Webhooks: collections use the payment signing secret, disbursements use the
 *   separate payout signing secret. Both are configured per webhook endpoint.
 */
class AfriqorePayPaymentProvider implements PaymentProviderDriver
{
    private const TOKEN_CACHE_PREFIX = 'afriqorepay:access_token:';

    private const DEFAULT_BASE_URL = 'https://pay.afriqorepay.com/api/v2';

    public function __construct(private PaymentProvider $provider) {}

    public function slug(): string
    {
        return PaymentProvider::SLUG_AFRIQOREPAY;
    }

    public function initiateCollection(PlatformPayment $payment): ProviderChargeResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'AFRIQOREPAY-STUB-'.strtoupper(Str::random(10)),
                message: 'AfriqorePay collection simulated (auto-paid mode)',
                raw: ['mode' => 'simulated'],
            );
        }

        $customerName = trim((string) data_get($payment->metadata, 'customer_name', ''));

        $body = array_filter([
            'amount' => (int) round((float) $payment->amount),
            'currency' => strtoupper((string) ($payment->currency ?: 'TZS')),
            'phone' => $this->normalizePhone((string) $payment->phone),
            'reference' => (string) $payment->reference,
            'message' => $customerName !== '' ? $customerName.' payment' : 'Payment '.$payment->reference,
            'callback_url' => $this->callbackUrl(),
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->send('/collections', $body, (string) $payment->reference, $payment);

        $data = $response->json() ?? [];

        if (($data['status'] ?? true) === false) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Unable to initiate AfriqorePay payment: '.$this->providerError($response),
                ],
            ]);
        }

        $transactionId = (string) ($data['transaction_id'] ?? $data['id'] ?? '');

        if ($transactionId === '') {
            throw ValidationException::withMessages([
                'payment' => ['AfriqorePay did not return a transaction_id.'],
            ]);
        }

        return new ProviderChargeResult(
            accepted: true,
            providerReference: $transactionId,
            message: (string) ($data['detail'] ?? 'Payment request sent'),
            raw: is_array($data) ? $data : [],
        );
    }

    public function validateWebhook(array $headers, array $payload, ?string $rawBody = null): bool
    {
        $secret = $this->webhookSecret($headers, $payload);

        if ($secret === '') {
            // Refuse unverified callbacks unless explicitly in local auto-paid mode.
            return (bool) config('platform.payment_auto_paid');
        }

        $signatureHeader = $this->headerValue($headers, 'Afriqore-Signature')
            ?: $this->headerValue($headers, 'X-Afriqore-Signature');

        if ($signatureHeader === '') {
            return false;
        }

        $body = $rawBody ?? (json_encode($payload) ?: '');

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $parts[strtolower($pair[0])][] = trim($pair[1]);
            }
        }

        $timestamp = $parts['t'][0] ?? '';
        if ($timestamp === '') {
            $timestamp = $this->headerValue($headers, 'Afriqore-Timestamp')
                ?: $this->headerValue($headers, 'X-Afriqore-Timestamp');
        }

        $signatures = $parts['v1']
            ?? $parts['sha256']
            ?? (isset($parts['signature']) ? $parts['signature'] : []);

        // A bare hex/base64 digest (no key=value structure).
        if ($signatures === [] && ! str_contains($signatureHeader, '=')) {
            $signatures = [trim($signatureHeader)];
        }

        if ($signatures === []) {
            return false;
        }

        if ($timestamp !== null && $timestamp !== '' && ctype_digit((string) $timestamp)) {
            $seconds = (int) $timestamp;
            if ($seconds > 99_999_999_999) {
                $seconds = (int) floor($seconds / 1000);
            }

            $tolerance = max(30, (int) config('services.afriqorepay.webhook_tolerance', 300));

            if (abs(now()->timestamp - $seconds) > $tolerance) {
                Log::warning('AfriqorePay webhook rejected: stale timestamp', [
                    'provider' => $this->provider->slug,
                    'timestamp' => $timestamp,
                ]);

                return false;
            }
        }

        $messages = [$body];
        if ($timestamp !== null && $timestamp !== '') {
            $messages[] = $timestamp.'.'.$body;
        }

        foreach ($signatures as $signature) {
            $signature = trim((string) $signature);
            $signature = preg_replace('/^sha256=/i', '', $signature) ?? $signature;

            if ($signature === '') {
                continue;
            }

            foreach ($messages as $message) {
                $hex = hash_hmac('sha256', $message, $secret);

                if (hash_equals($hex, strtolower($signature))) {
                    return true;
                }

                $raw = hex2bin($hex);
                if ($raw !== false && hash_equals(base64_encode($raw), $signature)) {
                    return true;
                }
            }
        }

        Log::warning('AfriqorePay webhook signature mismatch', [
            'provider' => $this->provider->slug,
            'event' => (string) ($payload['event'] ?? $payload['type'] ?? ''),
        ]);

        return false;
    }

    public function parseWebhook(array $payload): ProviderVerificationResult
    {
        $event = strtolower((string) ($payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? ''));

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $status = $this->normalizeStatus(
            $event,
            strtolower((string) ($data['status'] ?? $payload['status'] ?? ''))
        );

        $txRef = (string) (
            $data['reference']
            ?? $payload['reference']
            ?? $data['merchant_reference']
            ?? $payload['merchant_reference']
            ?? ''
        );

        $providerReference = (string) (
            $data['transaction_id']
            ?? $data['id']
            ?? $payload['transaction_id']
            ?? $payload['id']
            ?? ''
        );

        $amount = $data['amount'] ?? $payload['amount'] ?? null;
        $currency = $data['currency'] ?? $payload['currency'] ?? null;

        return new ProviderVerificationResult(
            status: $status,
            providerReference: $providerReference !== '' ? $providerReference : null,
            amount: is_numeric($amount) ? (float) $amount : null,
            currency: $currency !== null && $currency !== '' ? (string) $currency : null,
            txRef: $txRef !== '' ? $txRef : null,
            raw: $payload,
        );
    }

    public function verifyTransaction(PlatformPayment $payment): ProviderVerificationResult
    {
        $reference = (string) ($payment->external_reference ?: '');

        if ($this->shouldSimulate()) {
            return $this->pendingVerification($payment, $reference, ['mode' => 'unverified_auto_paid']);
        }

        if ($reference === '') {
            return $this->pendingVerification($payment, '', ['mode' => 'missing_transaction_id']);
        }

        // Verification runs inline before acknowledging a provider callback.
        $timeout = max(2, (int) config('services.afriqorepay.status_timeout', 8));

        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout($timeout)
                ->get($this->baseUrl().'/collections/'.rawurlencode($reference));
        } catch (ConnectionException $e) {
            Log::warning('AfriqorePay collection status unreachable', [
                'error' => $e->getMessage(),
                'transaction_id' => $reference,
                'reference' => $payment->reference,
            ]);

            return $this->pendingVerification($payment, $reference, [
                'mode' => 'provider_unavailable',
                'error' => $e->getMessage(),
            ]);
        }

        if ($response->serverError()) {
            Log::warning('AfriqorePay collection status unavailable', [
                'status' => $response->status(),
                'transaction_id' => $reference,
                'reference' => $payment->reference,
            ]);

            return $this->pendingVerification($payment, $reference, [
                'mode' => 'provider_unavailable',
                'status' => $response->status(),
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify AfriqorePay transaction.'],
            ]);
        }

        $data = $response->json() ?? [];

        return new ProviderVerificationResult(
            status: $this->normalizeStatus('', strtolower((string) ($data['status'] ?? 'pending'))),
            providerReference: (string) ($data['transaction_id'] ?? $reference),
            amount: isset($data['amount']) ? (float) $data['amount'] : (float) $payment->amount,
            currency: isset($data['currency']) ? (string) $data['currency'] : $payment->currency,
            txRef: $payment->reference,
            raw: is_array($data) ? $data : [],
        );
    }

    public function initiatePayout(array $payout): ProviderChargeResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'AFRIQOREPAY-PAYOUT-STUB-'.strtoupper(Str::random(8)),
                message: 'AfriqorePay payout simulated (auto-paid mode)',
                raw: ['mode' => 'simulated'],
            );
        }

        $reference = (string) ($payout['reference'] ?? '');
        $phone = (string) ($payout['phone'] ?? $payout['destination_phone'] ?? '');

        if ($reference === '' || $phone === '') {
            throw ValidationException::withMessages([
                'payout' => ['AfriqorePay payouts require a reference and destination phone.'],
            ]);
        }

        $callbackUrl = (string) ($payout['callback_url'] ?? '');
        if ($callbackUrl === '') {
            $callbackUrl = (string) (
                $this->provider->setting('payout_callback_url', '')
                ?: config('services.afriqorepay.payout_callback_url', '')
            );
        }

        $body = array_filter([
            'amount' => (int) round((float) ($payout['amount'] ?? 0)),
            'currency' => strtoupper((string) ($payout['currency'] ?? 'TZS')),
            'phone' => $this->normalizePhone($phone),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->send('/disbursements', $body, $reference);

        $data = $response->json() ?? [];

        if (($data['status'] ?? true) === false) {
            throw ValidationException::withMessages([
                'payout' => [
                    'Unable to initiate AfriqorePay payout: '.$this->providerError($response),
                ],
            ]);
        }

        $transactionId = (string) ($data['transaction_id'] ?? $data['id'] ?? '');

        if ($transactionId === '') {
            throw ValidationException::withMessages([
                'payout' => ['AfriqorePay did not return a disbursement transaction_id.'],
            ]);
        }

        return new ProviderChargeResult(
            accepted: true,
            providerReference: $transactionId,
            message: (string) ($data['detail'] ?? 'Disbursement created'),
            raw: is_array($data) ? $data : [],
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $path, array $body, string $idempotencyKey, ?PlatformPayment $payment = null): Response
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout($this->timeout())
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($this->baseUrl().$path, $body);
        } catch (ConnectionException $e) {
            Log::warning('AfriqorePay request unreachable', [
                'path' => $path,
                'error' => $e->getMessage(),
                'reference' => $payment?->reference,
            ]);

            throw ValidationException::withMessages([
                'payment' => ['AfriqorePay is temporarily unavailable. Please try again in a moment.'],
            ]);
        }

        if (! $response->successful()) {
            Log::warning('AfriqorePay request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'reference' => $payment?->reference,
            ]);

            throw ValidationException::withMessages([
                'payment' => ['Unable to reach AfriqorePay: '.$this->providerError($response)],
            ]);
        }

        return $response;
    }

    private function accessToken(): string
    {
        $appId = $this->appId();
        $passkey = $this->passkey();

        if ($appId === '' || $passkey === '') {
            throw ValidationException::withMessages([
                'payment' => [
                    'AfriqorePay is not configured. Set AFRIQOREPAY_APP_ID and AFRIQOREPAY_PASSKEY, then clear config cache.',
                ],
            ]);
        }

        $cacheKey = self::TOKEN_CACHE_PREFIX.sha1($appId.'|'.$this->baseUrl());
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::withBasicAuth($appId, $passkey)
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($this->baseUrl().'/oauth/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('AfriqorePay OAuth unreachable', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'payment' => ['AfriqorePay is temporarily unavailable. Please try again in a moment.'],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to authenticate with AfriqorePay: '.$this->providerError($response)],
            ]);
        }

        $token = (string) ($response->json('access_token') ?? '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'payment' => ['AfriqorePay did not return an access token.'],
            ]);
        }

        $ttl = max(60, (int) ($response->json('expires_in', 3600)) - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function appId(): string
    {
        return (string) (
            $this->provider->credential('app_id', '')
            ?: config('services.afriqorepay.app_id', '')
        );
    }

    private function passkey(): string
    {
        return (string) (
            $this->provider->credential('passkey', '')
            ?: config('services.afriqorepay.passkey', '')
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) (
            $this->provider->credential('api_base_url', '')
            ?: config('services.afriqorepay.base_url', self::DEFAULT_BASE_URL)
        ), '/');
    }

    private function timeout(): int
    {
        return max(5, (int) (
            $this->provider->setting('timeout', config('services.afriqorepay.timeout', 30))
        ));
    }

    private function callbackUrl(): string
    {
        return (string) (
            $this->provider->setting('callback_url', '')
            ?: config('services.afriqorepay.callback_url', '')
        );
    }

    private function shouldSimulate(): bool
    {
        return (bool) config('platform.payment_auto_paid');
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

    private function normalizeStatus(string $event, string $status): string
    {
        $candidate = strtolower($status !== '' ? $status : $event);

        if (
            str_contains($candidate, 'paid')
            || str_contains($candidate, 'succeed')
            || str_contains($candidate, 'success')
            || str_contains($candidate, 'complete')
        ) {
            return 'successful';
        }

        if (str_contains($candidate, 'cancel')) {
            return 'cancelled';
        }

        if (
            str_contains($candidate, 'fail')
            || str_contains($candidate, 'declin')
        ) {
            return 'failed';
        }

        if (str_contains($candidate, 'refund')) {
            return 'refunded';
        }

        if (str_contains($candidate, 'disput')) {
            return 'disputed';
        }

        return 'pending';
    }

    private function pendingVerification(PlatformPayment $payment, string $providerReference, array $raw): ProviderVerificationResult
    {
        return new ProviderVerificationResult(
            status: 'pending',
            providerReference: $providerReference !== '' ? $providerReference : null,
            amount: (float) $payment->amount,
            currency: $payment->currency,
            txRef: $payment->reference,
            raw: $raw,
        );
    }

    private function providerError(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            foreach (['message', 'detail', 'error', 'error_message'] as $key) {
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
        }

        $body = trim((string) $response->body());

        if ($body === '') {
            return 'HTTP '.$response->status();
        }

        if (preg_match('/<(?:!doctype|html|head|body)\b/i', $body) === 1) {
            return 'Service unavailable (HTTP '.$response->status().')';
        }

        return Str::limit($body, 200);
    }

    private function webhookSecret(array $headers, array $payload): string
    {
        $event = $payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? '';
        if ($event === '') {
            $event = $this->headerValue($headers, 'Afriqore-Event')
                ?: $this->headerValue($headers, 'Afriqore-Event-Type');
        }
        $event = strtolower((string) $event);

        $isPayout = str_starts_with($event, 'disbursement.')
            || str_starts_with($event, 'payout.')
            || isset($payload['disbursement']);

        if ($isPayout) {
            return (string) (
                $this->provider->credential('payout_webhook_secret', '')
                ?: config('services.afriqorepay.payout_webhook_secret', '')
            );
        }

        return (string) (
            $this->provider->credential('webhook_secret', '')
            ?: config('services.afriqorepay.webhook_secret', '')
        );
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
