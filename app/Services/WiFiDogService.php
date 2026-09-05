<?php

namespace App\Services;

use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WiFiDogService
{
    public function __construct(
        private GatewayResolver $gatewayResolver,
        private CaptiveSessionService $captiveSessionService,
    ) {}

    public function login(Request $request): RedirectResponse
    {
        $gwId = (string) $request->query('gw_id', '');
        $gateway = $this->gatewayResolver->resolveActive($gwId);

        $this->touchGateway($gateway, $request);

        $session = $this->captiveSessionService->resolveOrCreate($gateway, [
            'ip' => $request->query('ip'),
            'mac' => $request->query('mac'),
            'ssid' => $request->query('ssid'),
            'gw_address' => $request->query('gw_address'),
            'gw_port' => $request->query('gw_port'),
            'url' => $request->query('url'),
        ]);

        if ($session->isAuthenticated()) {
            $gatewayAuthUrl = $this->captiveSessionService->gatewayAuthRedirectUrl($session);
            if ($gatewayAuthUrl) {
                Log::info('wifidog.login_redirect_gateway_auth', [
                    'gw_id' => $gateway->gateway_id,
                    'gateway_id' => $gateway->id,
                    'network_id' => $gateway->network_station_id,
                    'client_mac' => $session->client_mac,
                    'client_ip' => $session->client_ip,
                    'session_token_hash' => hash('sha256', $session->token),
                    'response_status' => 302,
                ]);

                return redirect()->away($gatewayAuthUrl);
            }
        }

        $portalUrl = $this->captiveSessionService->portalRedirectUrl($session);

        Log::info('wifidog.login_redirect_portal', [
            'gw_id' => $gateway->gateway_id,
            'gateway_id' => $gateway->id,
            'network_id' => $gateway->network_station_id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'subdomain' => $gateway->company?->subdomain,
            'response_status' => 302,
        ]);

        return redirect()->away($portalUrl);
    }

    public function auth(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $stage = (string) $request->query('stage', 'login');
        $mac = $request->query('mac');
        $ip = $request->query('ip');
        $gwId = $request->query('gw_id');

        if ($token === '') {
            return $this->authResponse(0);
        }

        if (filled($gwId)) {
            try {
                $gateway = $this->gatewayResolver->resolveActive((string) $gwId);
                $this->touchGateway($gateway, $request);

                $session = $this->captiveSessionService->findByToken($token);
                if ($session && (int) $session->network_device_id !== (int) $gateway->id) {
                    Log::info('wifidog.auth_denied', [
                        'reason' => 'gateway_token_mismatch',
                        'gw_id' => $gwId,
                        'gateway_id' => $gateway->id,
                        'session_token_hash' => hash('sha256', $token),
                    ]);

                    return $this->authResponse(0);
                }
            } catch (HttpException) {
                return $this->authResponse(0);
            }
        }

        $allowed = $this->captiveSessionService->authorizeToken(
            $token,
            filled($mac) ? (string) $mac : null,
            filled($ip) ? (string) $ip : null,
        );

        // counters stage still requires a valid authenticated session
        if ($stage === 'logout') {
            if ($session = $this->captiveSessionService->findByToken($token)) {
                $session->forceFill([
                    'status' => CaptiveSession::STATUS_DISCONNECTED,
                    'last_seen_at' => now(),
                ])->save();
            }

            return $this->authResponse(0);
        }

        return $this->authResponse($allowed ? 1 : 0);
    }

    public function ping(Request $request): Response
    {
        $gwId = (string) $request->query('gw_id', '');

        try {
            $gateway = $this->gatewayResolver->resolveActive($gwId);
            $this->touchGateway($gateway, $request);

            Log::info('wifidog.ping', [
                'gw_id' => $gateway->gateway_id,
                'gateway_id' => $gateway->id,
                'network_id' => $gateway->network_station_id,
                'sys_uptime' => $request->query('sys_uptime'),
                'wifidog_uptime' => $request->query('wifidog_uptime'),
                'response_status' => 200,
            ]);
        } catch (HttpException $e) {
            Log::info('wifidog.ping_rejected', [
                'gw_id' => $gwId,
                'message' => $e->getMessage(),
                'response_status' => $e->getStatusCode(),
            ]);

            throw $e;
        }

        return response('Pong', 200)->header('Content-Type', 'text/plain');
    }

    private function touchGateway(NetworkDevice $gateway, Request $request): void
    {
        $gateway->forceFill(['last_seen_at' => now()])->save();
    }

    private function authResponse(int $code): Response
    {
        return response('Auth: '.$code, 200)->header('Content-Type', 'text/plain');
    }
}
