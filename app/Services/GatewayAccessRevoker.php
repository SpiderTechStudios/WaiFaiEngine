<?php

namespace App\Services;

use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayAccessRevoker
{
    public function __construct(private MikroTikService $mikroTik) {}

    /**
     * Remove a client's access from the router that owns the captive session.
     *
     * @return array<string, mixed>
     */
    public function revoke(CaptiveSession $session): array
    {
        $session->loadMissing('networkDevice');

        $router = $session->networkDevice;

        if (! $router) {
            return ['gateway_type' => null, 'revoked' => false, 'reason' => 'no_router'];
        }

        return match ($router->gateway_type) {
            NetworkDevice::GATEWAY_MIKROTIK => [
                'gateway_type' => $router->gateway_type,
                'revoked' => true,
                'result' => $this->mikroTik->revoke($session),
            ],
            NetworkDevice::GATEWAY_RUIJIE,
            NetworkDevice::GATEWAY_WAVLINK => $this->revokeWiFiDog($session, $router),
            default => $this->unsupported($router),
        };
    }

    /**
     * WiFiDog gateways (Ruijie, Wavlink, etc.) deauthorize a client with:
     *   GET http://{gw_address}:{gw_port}/wifidog/auth?logout=1&token={token}
     */
    private function revokeWiFiDog(CaptiveSession $session, NetworkDevice $router): array
    {
        $address = $session->gw_address ?: $router->lan_ip;
        $port = $session->gw_port ?: $router->wifidog_port ?: 2060;

        if (blank($address) || blank($session->token)) {
            return [
                'gateway_type' => $router->gateway_type,
                'revoked' => false,
                'reason' => 'missing_gateway_address_or_token',
            ];
        }

        $url = 'http://'.$address.':'.$port.'/wifidog/auth';

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->get($url, [
                    'logout' => 1,
                    'token' => $session->token,
                ]);

            // Some firmwares expose /wifidog/auth/ with a trailing slash only.
            if ($response->status() === 404) {
                $response = Http::timeout(5)
                    ->withoutVerifying()
                    ->get($url.'/', [
                        'logout' => 1,
                        'token' => $session->token,
                    ]);
            }

            return [
                'gateway_type' => $router->gateway_type,
                'revoked' => $response->successful(),
                'status' => $response->status(),
                'url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::warning('wifidog.logout_failed', [
                'captive_session_id' => $session->id,
                'router_id' => $router->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'gateway_type' => $router->gateway_type,
                'revoked' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function unsupported(NetworkDevice $router): array
    {
        Log::warning('access.revoke_unsupported_gateway', [
            'router_id' => $router->id,
            'gateway_type' => $router->gateway_type,
        ]);

        return [
            'gateway_type' => $router->gateway_type,
            'revoked' => false,
            'reason' => 'unsupported_gateway',
        ];
    }
}
