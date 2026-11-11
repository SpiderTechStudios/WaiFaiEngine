<?php

namespace App\Services\Omada;

use App\Models\OmadaIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Omada external captive-portal authorization.
 *
 * Uses the documented operator flow (Controller 5.0.15+):
 *   POST /{omadacId}/api/v2/hotspot/login          -> CSRF token + session cookie
 *   POST /{omadacId}/api/v2/hotspot/extPortal/auth -> authorize client
 *
 * Deauthorization uses the documented Open API endpoint:
 *   DELETE /openapi/v1/{omadacId}/sites/{siteId}/clients/{clientMac}
 */
class OmadaAuthenticationService
{
    /**
     * @return array{token: string, cookie: string}
     */
    public function operatorLogin(OmadaIntegration $integration): array
    {
        $username = (string) $integration->credential('operator_username', '');
        $password = (string) $integration->credential('operator_password', '');

        if ($username === '' || $password === '') {
            throw new OmadaException('The Omada Hotspot Operator username and password are required for external portal authorization.');
        }

        $controllerId = $this->client($integration)->controllerId();
        $url = rtrim((string) $integration->controller_url, '/').'/'.$controllerId.'/api/v2/hotspot/login';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(max(2, (int) config('omada.timeout', 15)))
                ->connectTimeout(max(1, (int) config('omada.connect_timeout', 8)))
                ->withOptions(['verify' => (bool) $integration->verify_tls && (bool) config('omada.verify_tls', true)])
                ->post($url, ['name' => $username, 'password' => $password]);
        } catch (ConnectionException $exception) {
            throw new OmadaException('Unable to reach the Omada controller for hotspot login.', context: ['transport' => $exception->getMessage()]);
        }

        $json = $response->json();
        $errorCode = is_array($json) ? (int) ($json['errorCode'] ?? -1) : -1;

        if ($response->failed() || $errorCode !== 0) {
            throw new OmadaException(
                'The Omada Hotspot Operator login failed. Check the operator username, password, and permissions.',
                errorCode: $errorCode,
                httpStatus: $response->status(),
                context: ['endpoint' => '/api/v2/hotspot/login'],
            );
        }

        $token = (string) ($json['result']['token'] ?? '');

        if ($token === '') {
            throw new OmadaException('The Omada controller did not return a hotspot CSRF token.', context: ['endpoint' => '/api/v2/hotspot/login']);
        }

        return [
            'token' => $token,
            'cookie' => $this->cookieFromResponse($response->headers()),
        ];
    }

    /**
     * @param  array<string, mixed>  $params  clientMac, apMac, ssidName, radioId, site
     */
    public function authorizeClient(OmadaIntegration $integration, array $params, int $durationSeconds): array
    {
        $clientMac = $this->normalizeMac((string) ($params['client_mac'] ?? $params['clientMac'] ?? ''));

        if ($clientMac === '') {
            throw new OmadaException('A client MAC address is required to authorize an Omada client.');
        }

        $login = $this->operatorLogin($integration);
        $controllerId = $this->client($integration)->controllerId();
        $url = rtrim((string) $integration->controller_url, '/').'/'.$controllerId.'/api/v2/hotspot/extPortal/auth';

        $site = (string) ($params['site'] ?? $integration->site_name ?? '');

        $body = array_filter([
            'clientMac' => $clientMac,
            'apMac' => $this->normalizeMac((string) ($params['ap_mac'] ?? $params['apMac'] ?? '')),
            'gatewayMac' => $this->normalizeMac((string) ($params['gateway_mac'] ?? $params['gatewayMac'] ?? '')),
            'ssidName' => $params['ssid_name'] ?? $params['ssidName'] ?? null,
            'radioId' => $params['radio_id'] ?? $params['radioId'] ?? null,
            'vid' => $params['vid'] ?? null,
            'site' => $site !== '' ? $site : null,
            'time' => (int) round((time() + max(60, $durationSeconds)) * 1000),
            'authType' => (int) config('omada.auth_type', 4),
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(max(2, (int) config('omada.timeout', 15)))
                ->connectTimeout(max(1, (int) config('omada.connect_timeout', 8)))
                ->withOptions(['verify' => (bool) $integration->verify_tls && (bool) config('omada.verify_tls', true)])
                ->withHeaders(array_filter([
                    'Csrf-Token' => $login['token'],
                    'Cookie' => $login['cookie'] ?: null,
                ]))
                ->post($url, $body);
        } catch (ConnectionException $exception) {
            throw new OmadaException('Unable to reach the Omada controller to authorize the client.', context: ['transport' => $exception->getMessage()]);
        }

        $json = $response->json();
        $errorCode = is_array($json) ? (int) ($json['errorCode'] ?? -1) : -1;

        if ($response->failed() || $errorCode !== 0) {
            throw new OmadaException(
                'The Omada controller did not authorize the client: '.($json['msg'] ?? 'unknown error'),
                errorCode: $errorCode,
                httpStatus: $response->status(),
                context: ['endpoint' => '/api/v2/hotspot/extPortal/auth'],
            );
        }

        return [
            'authorized' => true,
            'client_mac' => $clientMac,
            'expires_at' => now()->addSeconds(max(60, $durationSeconds)),
            'raw' => is_array($json) ? ($json['result'] ?? $json) : [],
        ];
    }

    public function disconnectClient(OmadaIntegration $integration, string $clientMac): bool
    {
        $mac = $this->normalizeMac($clientMac);

        if ($mac === '') {
            throw new OmadaException('A client MAC address is required to disconnect an Omada client.');
        }

        if (blank($integration->site_id)) {
            throw new OmadaException('Select an Omada site before disconnecting clients.');
        }

        $client = $this->client($integration);

        $client->delete(
            '/openapi/v1/'.$client->controllerId()
            .'/sites/'.$integration->site_id
            .'/clients/'.rawurlencode($mac)
        );

        return true;
    }

    private function client(OmadaIntegration $integration): OmadaControllerClient
    {
        return new OmadaControllerClient($integration);
    }

    public function normalizeMac(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');

        if (strlen($hex) !== 12) {
            return '';
        }

        return implode('-', str_split($hex, 2));
    }

    /**
     * @param  array<string, array<int, string>|string>  $headers
     */
    private function cookieFromResponse(array $headers): string
    {
        $cookies = [];

        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) !== 'set-cookie') {
                continue;
            }

            foreach ((array) $values as $value) {
                $pair = trim(explode(';', (string) $value, 2)[0]);
                if ($pair !== '') {
                    $cookies[] = $pair;
                }
            }
        }

        return implode('; ', $cookies);
    }
}
