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
    ) {
    }

    public function login(Request $request): RedirectResponse
    {

        $gwId = $this->extractGatewayId($request);
Log::info('request from mobile', $request->all());


        Log::info('wifidog.login_request', [
            'received_gw_id' => $gwId,
            'query' => [
                'gw_id' => $request->query('gw_id'),
                'dev_id' => $request->query('dev_id'),
                'ip' => $request->query('ip'),
                'mac' => $request->query('mac'),
                'gw_address' => $request->query('gw_address'),
                'gw_port' => $request->query('gw_port'),
                'ssid' => $request->query('ssid'),
            ],
        ]);

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

        try {
            $portalUrl = $this->captiveSessionService->portalRedirectUrl($session);
        } catch (\RuntimeException $e) {
            Log::error('wifidog.portal_url_misconfigured', [
                'gw_id' => $gateway->gateway_id,
                'gateway_id' => $gateway->id,
                'message' => $e->getMessage(),
            ]);

            throw new HttpException(500, $e->getMessage());
        }

        Log::info('wifidog.login_redirect_portal', [
            'gw_id' => $gateway->gateway_id,
            'gateway_id' => $gateway->id,
            'network_id' => $gateway->network_station_id,
            'company_id' => $gateway->company_id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'captive_session_id' => $session->id,
            'session_token_hash' => hash('sha256', $session->token),
            'subdomain' => $gateway->company?->subdomain,
            'generated_portal_url' => preg_replace('/([?&]session=)[a-f0-9]+/i', '$1***', $portalUrl),
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
        $gwId = $request->query('gw_id') ?: $request->query('dev_id');

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

    /**
     * WiFiDog PortalScriptPathFragment (portal/).
     *
     * On Ruijie/Reyee APs the configured Portal Server URL is this endpoint, so
     * it receives BOTH the initial redirect for denied clients and the
     * post-auth redirect after the gateway returns "Auth: 1".
     *
     * - Fresh / denied client -> 302 to the frontend connect page (pay here).
     * - Authenticated client  -> 302 to the original url param (e.g. google.com).
     *
     * Redirecting a denied client straight to google.com makes the AP intercept
     * it again, producing an endless "too many redirects" loop.
     */
    public function portal(Request $request): RedirectResponse
    {
        $gwId = $this->extractGatewayId($request);
        $token = (string) $request->query('token', '');

        Log::info('wifidog.portal_request', [
            'received_gw_id' => $gwId,
            'query' => [
                'gw_id' => $request->query('gw_id'),
                'dev_id' => $request->query('dev_id'),
                'ip' => $request->query('ip'),
                'mac' => $request->query('mac'),
                'gw_address' => $request->query('gw_address'),
                'gw_port' => $request->query('gw_port'),
                'ssid' => $request->query('ssid'),
                'url' => $request->query('url'),
            ],
        ]);

        $gateway = $this->gatewayResolver->resolveActive($gwId);

        $this->touchGateway($gateway, $request);

        $session = $token !== '' ? $this->captiveSessionService->findByToken($token) : null;

        if ($session && (int) $session->network_device_id !== (int) $gateway->id) {
            $session = null;
        }

        if (! $session) {
            $session = $this->captiveSessionService->resolveOrCreate($gateway, [
                'ip' => $request->query('ip'),
                'mac' => $request->query('mac'),
                'ssid' => $request->query('ssid'),
                'gw_address' => $request->query('gw_address'),
                'gw_port' => $request->query('gw_port'),
                'url' => $request->query('url'),
            ]);
        }

        if ($session->isAuthenticated()) {
            $target = $this->resolvePortalTargetUrl($session, $request->query('url'));

            Log::info('wifidog.portal_redirect_authenticated', [
                'gw_id' => $gateway->gateway_id,
                'gateway_id' => $gateway->id,
                'network_id' => $gateway->network_station_id,
                'client_mac' => $session->client_mac,
                'client_ip' => $session->client_ip,
                'session_token_hash' => hash('sha256', $session->token),
                'target_host' => parse_url($target, PHP_URL_HOST),
                'response_status' => 302,
            ]);

            return redirect()->away($target);
        }

        try {
            $portalUrl = $this->captiveSessionService->portalRedirectUrl($session, [
                'mac' => $session->client_mac,
                'ip' => $session->client_ip,
                'gw_address' => $session->gw_address,
                'gw_port' => $session->gw_port,
            ]);
        } catch (\RuntimeException $e) {
            Log::error('wifidog.portal_url_misconfigured', [
                'gw_id' => $gateway->gateway_id,
                'gateway_id' => $gateway->id,
                'message' => $e->getMessage(),
            ]);

            throw new HttpException(500, $e->getMessage());
        }

        Log::info('wifidog.portal_redirect_captive', [
            'gw_id' => $gateway->gateway_id,
            'gateway_id' => $gateway->id,
            'network_id' => $gateway->network_station_id,
            'company_id' => $gateway->company_id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'captive_session_id' => $session->id,
            'session_token_hash' => hash('sha256', $session->token),
            'generated_portal_url' => preg_replace('/([?&]session=)[a-f0-9]+/i', '$1***', $portalUrl),
            'response_status' => 302,
        ]);

        return redirect()->away($portalUrl);
    }

    /**
     * Prefer the original URL captured for the session, then the gateway-supplied
     * url param, finally the configured success URL.
     */
    private function resolvePortalTargetUrl(CaptiveSession $session, mixed $requestedUrl = null): string
    {
        $candidates = [];

        if (filled($session->requested_url)) {
            $candidates[] = (string) $session->requested_url;
        }

        if (is_string($requestedUrl) && filled($requestedUrl)) {
            $candidates[] = $requestedUrl;
        }

        foreach ($candidates as $candidate) {
            if ($this->isSafeRedirectUrl($candidate)) {
                return $candidate;
            }
        }

        $fallback = (string) config('captive.portal_success_url', 'http://www.google.com');

        return $this->isSafeRedirectUrl($fallback) ? $fallback : 'http://www.google.com';
    }

    private function isSafeRedirectUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    public function ping(Request $request): Response
    {
        $gwId = $this->extractGatewayId($request);

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

    /**
     * WiFiDog v1 uses gw_id; some Ruijie builds also send dev_id.
     */
    private function extractGatewayId(Request $request): string
    {
        $gwId = trim((string) $request->query('gw_id', ''));
        if ($gwId !== '') {
            return $gwId;
        }

        return trim((string) $request->query('dev_id', ''));
    }

    private function authResponse(int $code): Response
    {
        return response('Auth: ' . $code, 200)->header('Content-Type', 'text/plain');
    }
}
