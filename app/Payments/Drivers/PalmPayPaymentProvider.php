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
use OpenSSLAsymmetricKey;

class PalmPayPaymentProvider implements PaymentProviderDriver
{
    public function __construct(private PaymentProvider $provider) {}

    public function slug(): string
    {
        return PaymentProvider::SLUG_PALMPAY;
    }

    public function initiateCollection(PlatformPayment $payment): ProviderChargeResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'PALMPAY-STUB-'.strtoupper(Str::random(10)),
                message: 'PalmPay charge simulated (missing credentials / auto-paid mode)',
                raw: [
                    'mode' => 'simulated',
                    'checkoutUrl' => null,
                ],
            );
        }

        $orderId = $this->merchantOrderId($payment->reference);
        $body = $this->baseSignedFields([
            'orderId' => $orderId,
            'title' => (string) data_get($payment->metadata, 'title', 'WaiFai payment'),
            'description' => (string) data_get(
                $payment->metadata,
                'description',
                $payment->resolvePurpose()
            ),
            'amount' => $this->toMinorUnits((float) $payment->amount),
            'currency' => strtoupper((string) $payment->currency),
            'notifyUrl' => (string) $this->provider->setting(
                'notify_url',
                rtrim((string) config('app.url'), '/').'/api/v1/webhooks/payments/'.$this->provider->slug
            ),
            'callBackUrl' => (string) $this->provider->setting(
                'redirect_url',
                config('app.url').'/payment/complete'
            ),
            'productType' => (string) $this->provider->setting('product_type', 'bank_transfer'),
            'goodsDetails' => (string) $this->provider->setting(
                'goods_details',
                '[{"goodsId":"-1"}]'
            ),
            'userMobileNo' => $this->normalizePhone((string) $payment->phone),
            'remark' => (string) data_get($payment->metadata, 'remark', $payment->resolvePurpose()),
        ]);

        if ($userId = data_get($payment->metadata, 'customer_id')) {
            $body['userId'] = (string) $userId;
        }

        $response = $this->signedPost('/api/v2/payment/merchant/createorder', $body);

        if (! $response->successful() || (string) $response->json('respCode') !== '00000000') {
            throw ValidationException::withMessages([
                'payment' => [
                    'Unable to initiate PalmPay payment: '.($response->json('respMsg') ?? $response->body()),
                ],
            ]);
        }

        $data = $response->json('data') ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        return new ProviderChargeResult(
            accepted: true,
            providerReference: (string) ($data['orderNo'] ?? ''),
            message: (string) ($response->json('respMsg') ?? 'PalmPay order created'),
            raw: array_merge($data, [
                'merchant_order_id' => $orderId,
                'checkoutUrl' => $data['checkoutUrl'] ?? null,
                'virtual_account' => array_filter([
                    'account_number' => $data['payerVirtualAccNo'] ?? null,
                    'bank_name' => $data['payerBankName'] ?? null,
                    'account_name' => $data['payerAccountName'] ?? null,
                    'account_id' => $data['payerAccountId'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ]),
        );
    }

    public function validateWebhook(array $headers, array $payload, ?string $rawBody = null): bool
    {
        $publicKey = (string) $this->provider->credential(
            'public_key',
            config('services.palmpay.public_key', '')
        );

        if ($publicKey === '') {
            return (bool) config('platform.payment_auto_paid') || $this->shouldSimulate();
        }

        $sign = (string) ($payload['sign'] ?? '');
        if ($sign === '') {
            return false;
        }

        $sign = urldecode($sign);
        $params = $payload;
        unset($params['sign']);

        $md5 = strtoupper(md5($this->buildSignContent($params)));
        $key = $this->loadPublicKey($publicKey);

        return openssl_verify($md5, base64_decode($sign, true) ?: '', $key, OPENSSL_ALGO_SHA1) === 1;
    }

    public function parseWebhook(array $payload): ProviderVerificationResult
    {
        $orderStatus = (int) ($payload['orderStatus'] ?? 0);

        return new ProviderVerificationResult(
            status: $this->mapOrderStatus($orderStatus),
            providerReference: isset($payload['orderNo']) ? (string) $payload['orderNo'] : null,
            amount: isset($payload['amount']) ? $this->fromMinorUnits((float) $payload['amount']) : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            txRef: (string) ($payload['orderId'] ?? ''),
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

        $body = $this->baseSignedFields(array_filter([
            'orderId' => $this->merchantOrderId($payment->reference),
            'orderNo' => $payment->external_reference ?: null,
        ], fn ($value) => filled($value)));

        $response = $this->signedPost('/api/v2/payment/merchant/order/queryStatus', $body);

        if (! $response->successful() || (string) $response->json('respCode') !== '00000000') {
            throw ValidationException::withMessages([
                'payment' => ['Unable to verify PalmPay transaction.'],
            ]);
        }

        $data = $response->json('data') ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        $orderStatus = (int) ($data['orderStatus'] ?? 0);
        $amount = $data['amount'] ?? $data['orderAmount'] ?? null;

        return new ProviderVerificationResult(
            status: $this->mapOrderStatus($orderStatus),
            providerReference: isset($data['orderNo']) ? (string) $data['orderNo'] : $payment->external_reference,
            amount: $amount !== null ? $this->fromMinorUnits((float) $amount) : (float) $payment->amount,
            currency: isset($data['currency']) ? (string) $data['currency'] : $payment->currency,
            txRef: (string) ($data['orderId'] ?? $payment->reference),
            raw: $data,
        );
    }

    public function initiatePayout(array $payout): ProviderChargeResult
    {
        if ($this->shouldSimulate()) {
            return new ProviderChargeResult(
                accepted: true,
                providerReference: 'PALMPAY-PAYOUT-STUB-'.strtoupper(Str::random(8)),
                message: 'PalmPay payout simulated',
                raw: ['mode' => 'simulated'],
            );
        }

        $path = (string) $this->provider->setting(
            'payout_path',
            '/api/v2/payment/merchant/payout'
        );

        $body = $this->baseSignedFields($payout);
        if (isset($body['amount']) && is_numeric($body['amount'])) {
            $amount = (float) $body['amount'];
            $body['amount'] = ($payout['amount_is_minor'] ?? false)
                ? (int) round($amount)
                : $this->toMinorUnits($amount);
        }
        unset($body['amount_is_minor']);

        $response = $this->signedPost($path, $body);

        if (! $response->successful() || (string) $response->json('respCode') !== '00000000') {
            throw ValidationException::withMessages([
                'payout' => [
                    'Unable to initiate PalmPay payout: '.($response->json('respMsg') ?? $response->body()),
                ],
            ]);
        }

        $data = $response->json('data') ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        return new ProviderChargeResult(
            accepted: true,
            providerReference: (string) ($data['orderNo'] ?? $data['orderId'] ?? ''),
            message: (string) ($response->json('respMsg') ?? 'PalmPay payout initiated'),
            raw: $data,
        );
    }

    private function shouldSimulate(): bool
    {
        if (config('platform.payment_auto_paid')) {
            return true;
        }

        $appId = (string) $this->provider->credential('app_id', config('services.palmpay.app_id', ''));
        $privateKey = (string) $this->provider->credential('private_key', config('services.palmpay.private_key', ''));

        return $appId === '' || $privateKey === '';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function baseSignedFields(array $fields): array
    {
        return array_merge([
            'requestTime' => (int) floor(microtime(true) * 1000),
            'version' => (string) $this->provider->setting(
                'version',
                config('services.palmpay.version', 'V2')
            ),
            'nonceStr' => bin2hex(random_bytes(16)),
        ], $fields);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function signedPost(string $path, array $body): \Illuminate\Http\Client\Response
    {
        $appId = (string) $this->provider->credential('app_id', config('services.palmpay.app_id'));
        $privateKey = (string) $this->provider->credential('private_key', config('services.palmpay.private_key'));
        $baseUrl = rtrim((string) $this->provider->credential(
            'api_base_url',
            config('services.palmpay.base_url', 'https://open-gw-prod.palmpay-inc.com')
        ), '/');
        $countryCode = (string) $this->provider->setting(
            'country_code',
            config('services.palmpay.country_code', 'NG')
        );

        $signature = $this->sign($body, $privateKey);

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$appId,
            'CountryCode' => $countryCode,
            'Signature' => $signature,
        ])
            ->timeout(30)
            ->post($baseUrl.$path, $body);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function sign(array $params, string $privateKeyPemOrBase64): string
    {
        $md5 = strtoupper(md5($this->buildSignContent($params)));
        $key = $this->loadPrivateKey($privateKeyPemOrBase64);

        if (! openssl_sign($md5, $signature, $key, OPENSSL_ALGO_SHA1)) {
            throw ValidationException::withMessages([
                'payment' => ['Unable to sign PalmPay request with the configured private key.'],
            ]);
        }

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildSignContent(array $params): string
    {
        $filtered = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $filtered[(string) $key] = trim((string) $value);
        }

        ksort($filtered, SORT_STRING);

        $parts = [];
        foreach ($filtered as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return implode('&', $parts);
    }

    private function loadPrivateKey(string $key): OpenSSLAsymmetricKey
    {
        $key = trim(str_replace(["\r\n", "\r"], "\n", $key));

        if (! str_contains($key, 'BEGIN')) {
            $chunked = trim(chunk_split(preg_replace('/\s+/', '', $key) ?: '', 64, "\n"));
            foreach ([
                "-----BEGIN PRIVATE KEY-----\n{$chunked}\n-----END PRIVATE KEY-----",
                "-----BEGIN RSA PRIVATE KEY-----\n{$chunked}\n-----END RSA PRIVATE KEY-----",
            ] as $pem) {
                $resource = openssl_pkey_get_private($pem);
                if ($resource instanceof OpenSSLAsymmetricKey) {
                    return $resource;
                }
            }
        }

        $resource = openssl_pkey_get_private($key);
        if (! $resource instanceof OpenSSLAsymmetricKey) {
            throw ValidationException::withMessages([
                'payment' => ['Invalid PalmPay merchant private key.'],
            ]);
        }

        return $resource;
    }

    private function loadPublicKey(string $key): OpenSSLAsymmetricKey
    {
        $key = trim(str_replace(["\r\n", "\r"], "\n", $key));

        if (! str_contains($key, 'BEGIN')) {
            $chunked = trim(chunk_split(preg_replace('/\s+/', '', $key) ?: '', 64, "\n"));
            foreach ([
                "-----BEGIN PUBLIC KEY-----\n{$chunked}\n-----END PUBLIC KEY-----",
                "-----BEGIN RSA PUBLIC KEY-----\n{$chunked}\n-----END RSA PUBLIC KEY-----",
            ] as $pem) {
                $resource = openssl_pkey_get_public($pem);
                if ($resource instanceof OpenSSLAsymmetricKey) {
                    return $resource;
                }
            }
        }

        $resource = openssl_pkey_get_public($key);
        if (! $resource instanceof OpenSSLAsymmetricKey) {
            throw ValidationException::withMessages([
                'payment' => ['Invalid PalmPay public key.'],
            ]);
        }

        return $resource;
    }

    private function toMinorUnits(float $amount): int
    {
        $factor = (int) $this->provider->setting('minor_unit_factor', 100);

        return (int) round($amount * max(1, $factor));
    }

    private function fromMinorUnits(float $minorAmount): float
    {
        $factor = (int) $this->provider->setting('minor_unit_factor', 100);

        return round($minorAmount / max(1, $factor), 2);
    }

    private function mapOrderStatus(int $orderStatus): string
    {
        return match ($orderStatus) {
            2 => 'successful',
            3 => 'failed',
            4 => 'cancelled',
            default => 'pending',
        };
    }

    private function merchantOrderId(string $reference): string
    {
        // PalmPay orderId max length is 32.
        return Str::limit($reference, 32, '');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return $digits;
    }
}
