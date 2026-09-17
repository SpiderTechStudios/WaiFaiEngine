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
    /**
     * Dedicated channel (storage/logs/wifidog.log) so the whole captive-portal
     * flow can be followed in one place:
     *
     *   mobile -> AP -> API (login/portal)   [direction: mobile->api]
     *   AP -> API   (auth/ping)              [direction: ap->api]
     *   API -> AP   (redirect to /wifidog/auth) [direction: api->ap]
     *   API -> mobile (redirect to /connect or original URL) [direction: api->mobile]
     */
    private const LOG_CHANNEL = 'wifidog';

    public function __construct(
        private GatewayResolver $gatewayResolver,
        private CaptiveSessionService $captiveSessionService,
    ) {
    }

    public function login(Request $request): RedirectResponse
    {
        $gwId = $this->extractGatewayId($request);

        $this->trace('login.in', $this->requestContext($request, 'login'));

        $gateway = $this->gatewayResolver->resolveActive($gwId);
        $this->touchGateway($gateway, $request);

        $this->trace('login.gateway', $this->gatewayContext($gateway));

        $session = $this->captiveSessionService->resolveOrCreate($gateway, [
            'ip' => $request->query('ip'),
            'mac' => $request->query('mac'),
            'ssid' => $request->query('ssid'),
            'gw_address' => $request->query('gw_address'),
            'gw_port' => $request->query('gw_port'),
            'url' => $request->query('url'),
        ]);

        $this->trace('login.session', $this->sessionContext($session));

        if ($session->isAuthenticated()) {
            $gatewayAuthUrl = $this->captiveSessionService->gatewayAuthRedirectUrl($session);

            if ($gatewayAuthUrl) {
                $this->trace('login.out', [
                    'direction' => 'api->ap',
                    'reason' => 'session_authenticated',
                    'result' => 'redirect_gateway_auth',
                    'captive_session_id' => $session->id,
                    'client_mac' => $session->client_mac,
                    'client_ip' => $session->client_ip,
                    'session_token_hash' => hash('sha256', $session->token),
                    'redirect_to' => $this->maskQueryValue($gatewayAuthUrl, 'token'),
                    'response_status' => 302,
                ]);

                return redirect()->away($gatewayAuthUrl);
            }

            $this->trace('login.warning', [
                'reason' => 'gateway_auth_url_missing',
                'captive_session_id' => $session->id,
                'gw_address' => $session->gw_address,
                'gw_port' => $session->gw_port,
            ]);
        }

        try {
            $portalUrl = $this->captiveSessionService->portalRedirectUrl($session);
        } catch (\RuntimeException $e) {
            $this->trace('login.error', [
                'reason' => 'portal_url_misconfigured',
                'gateway_id' => $gateway->id,
                'message' => $e->getMessage(),
            ]);

            throw new HttpException(500, $e->getMessage());
        }

        $this->trace('login.out', [
            'direction' => 'api->mobile',
            'reason' => 'session_not_authenticated',
            'result' => 'redirect_captive_portal',
            'captive_session_id' => $session->id,
            'session_status' => $session->status,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'subdomain' => $gateway->company?->subdomain,
            'redirect_to' => $this->maskQueryValue($portalUrl, 'session'),
            'response_status' => 302,
        ]);

        return redirect()->away($portalUrl);
    }

    /**
     * WiFiDog auth protocol. The gateway calls this server-to-server after the
     * phone opens http://{gw_address}:{gw_port}/wifidog/auth?token=...
     *
     * The token is looked up against captive_sessions.token; log the match so we
     * can see exactly why a paid session is (not) authorized.
     */
    public function auth(Request $request): Response
    {
        $token = trim((string) $request->query('token', ''));
        $tokenHash = $token !== '' ? hash('sha256', $token) : null;
        $stage = (string) $request->query('stage', 'login');

        $this->trace('auth.in', $this->requestContext($request, 'auth') + ['stage' => $stage]);

        if ($token === '') {
            $this->trace('auth.out', [
                'direction' => 'api->ap',
                'stage' => $stage,
                'auth_code' => 0,
                'reason' => 'empty_token',
            ]);

            return $this->authResponse(0);
        }

        $session = $this->captiveSessionService->findByToken($token);

        $this->trace('auth.token_lookup', [
            'direction' => 'ap->api',
            'table' => 'captive_sessions',
            'column' => 'token',
            'token_present' => true,
            'session_token_hash' => $tokenHash,
            'session_found' => $session !== null,
            'captive_session_id' => $session?->id,
            'session_status' => $session?->status,
            'session_expires_at' => $session?->expires_at?->toIso8601String(),
            'session_client_mac' => $session?->client_mac,
            'request_mac' => $request->query('mac'),
            'mac_matches' => $session && $request->query('mac')
                ? strcasecmp(
                    (string) preg_replace('/[^A-Fa-f0-9]/', '', (string) $session->client_mac),
                    (string) preg_replace('/[^A-Fa-f0-9]/', '', (string) $request->query('mac')),
                ) === 0
                : null,
        ]);

        $gwId = $request->query('gw_id') ?: $request->query('dev_id');

        if (filled($gwId)) {
            try {
                $gateway = $this->gatewayResolver->resolveActive((string) $gwId);
                $this->touchGateway($gateway, $request);

                if ($session && (int) $session->network_device_id !== (int) $gateway->id) {
                    $this->trace('auth.out', [
                        'direction' => 'api->ap',
                        'stage' => $stage,
                        'auth_code' => 0,
                        'reason' => 'gateway_token_mismatch',
                        'request_gw_id' => $gwId,
                        'session_gateway_id' => $session->network_device_id,
                        'gateway_id' => $gateway->id,
                        'session_token_hash' => $tokenHash,
                    ]);

                    return $this->authResponse(0);
                }
            } catch (HttpException $e) {
                $this->trace('auth.out', [
                    'direction' => 'api->ap',
                    'stage' => $stage,
                    'auth_code' => 0,
                    'reason' => 'unknown_gateway',
                    'request_gw_id' => $gwId,
                    'message' => $e->getMessage(),
                ]);

                return $this->authResponse(0);
            }
        }

        if ($stage === 'logout') {
            if ($session) {
                $session->forceFill([
                    'status' => CaptiveSession::STATUS_DISCONNECTED,
                    'last_seen_at' => now(),
                ])->save();
            }

            $this->trace('auth.out', [
                'direction' => 'api->ap',
                'stage' => $stage,
                'auth_code' => 0,
                'reason' => 'logout',
                'captive_session_id' => $session?->id,
                'session_token_hash' => $tokenHash,
            ]);

            return $this->authResponse(0);
        }

        $allowed = $this->captiveSessionService->authorizeToken(
            $token,
            filled($request->query('mac')) ? (string) $request->query('mac') : null,
            filled($request->query('ip')) ? (string) $request->query('ip') : null,
        );

        $this->trace('auth.out', [
            'direction' => 'api->ap',
            'stage' => $stage,
            'auth_code' => $allowed ? 1 : 0,
            'decision' => $allowed ? 'authorized' : 'denied',
            'captive_session_id' => $session?->id,
            'session_status' => $session?->status,
            'client_mac' => $request->query('mac'),
            'client_ip' => $request->query('ip'),
            'session_token_hash' => $tokenHash,
        ]);

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
     */
    public function portal(Request $request): RedirectResponse
    {
        $gwId = $this->extractGatewayId($request);
        $token = trim((string) $request->query('token', ''));

        $this->trace('portal.in', $this->requestContext($request, 'portal'));

        $gateway = $this->gatewayResolver->resolveActive($gwId);
        $this->touchGateway($gateway, $request);

        $this->trace('portal.gateway', $this->gatewayContext($gateway));

        $session = $token !== '' ? $this->captiveSessionService->findByToken($token) : null;

        $this->trace('portal.token_lookup', [
            'direction' => 'ap->api',
            'table' => 'captive_sessions',
            'column' => 'token',
            'token_present' => $token !== '',
            'session_token_hash' => $token !== '' ? hash('sha256', $token) : null,
            'session_found' => $session !== null,
            'captive_session_id' => $session?->id,
            'session_status' => $session?->status,
        ]);

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

            $this->trace('portal.session_resolved', $this->sessionContext($session));
        }

        $hasGatewayMessage = $request->filled('message');

        if ($session->isAuthenticated() && ! $hasGatewayMessage) {
            $target = $this->resolvePortalTargetUrl($session, $request->query('url'));

            $this->trace('portal.out', [
                'direction' => 'api->mobile',
                'reason' => 'session_authenticated',
                'result' => 'redirect_original_url',
                'captive_session_id' => $session->id,
                'client_mac' => $session->client_mac,
                'client_ip' => $session->client_ip,
                'session_token_hash' => hash('sha256', $session->token),
                'redirect_to' => $target,
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
            $this->trace('portal.error', [
                'reason' => 'portal_url_misconfigured',
                'gateway_id' => $gateway->id,
                'message' => $e->getMessage(),
            ]);

            throw new HttpException(500, $e->getMessage());
        }

        $this->trace('portal.out', [
            'direction' => 'api->mobile',
            'reason' => $hasGatewayMessage ? 'gateway_message' : 'session_not_authenticated',
            'result' => 'redirect_captive_portal',
            'gateway_message' => $request->query('message'),
            'captive_session_id' => $session->id,
            'session_status' => $session->status,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'redirect_to' => $this->maskQueryValue($portalUrl, 'session'),
            'response_status' => 302,
        ]);

        return redirect()->away($portalUrl);
    }

    public function ping(Request $request): Response
    {
        $gwId = $this->extractGatewayId($request);

        $this->trace('ping.in', $this->requestContext($request, 'ping'));

        try {
            $gateway = $this->gatewayResolver->resolveActive($gwId);
            $this->touchGateway($gateway, $request);

            $this->trace('ping.out', [
                'direction' => 'api->ap',
                'reason' => 'ok',
                'gateway_id' => $gateway->id,
                'network_id' => $gateway->network_station_id,
                'gw_id' => $gateway->gateway_id,
                'sys_uptime' => $request->query('sys_uptime'),
                'wifidog_uptime' => $request->query('wifidog_uptime'),
                'response_status' => 200,
            ]);
        } catch (HttpException $e) {
            $this->trace('ping.out', [
                'direction' => 'api->ap',
                'reason' => 'gateway_rejected',
                'gw_id' => $gwId,
                'message' => $e->getMessage(),
                'response_status' => $e->getStatusCode(),
            ]);

            throw $e;
        }

        return response('Pong', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * The gateway daemon sends "User-Agent: WiFiDog <version>" for its
     * server-to-server auth/ping calls; everything else is the phone browser.
     */
    private function caller(Request $request): string
    {
        return str_contains((string) $request->userAgent(), 'WiFiDog') ? 'ap' : 'mobile';
    }

    private function traceKey(Request $request): string
    {
        $mac = (string) $request->query('mac', '');
        $cleanMac = strtoupper((string) preg_replace('/[^A-Fa-f0-9]/', '', $mac));

        if ($cleanMac !== '') {
            return 'mac:'.$cleanMac;
        }

        $token = (string) $request->query('token', '');

        if ($token !== '') {
            return 'token:'.substr(hash('sha256', $token), 0, 12);
        }

        return 'gw:'.$this->extractGatewayId($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(Request $request, string $flow): array
    {
        $caller = $this->caller($request);

        $query = $request->query();
        if (array_key_exists('token', $query)) {
            $query['token'] = '***';
        }

        return [
            'flow' => $flow,
            'direction' => $caller.'->api',
            'caller' => $caller,
            'trace_key' => $this->traceKey($request),
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'query' => $query,
            'client_ip' => $request->query('ip') ?? $request->ip(),
            'client_mac' => $request->query('mac'),
            'gw_id' => $this->extractGatewayId($request),
            'token_present' => filled($request->query('token')),
            'session_token_hash' => filled($request->query('token')) ? hash('sha256', (string) $request->query('token')) : null,
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gatewayContext(NetworkDevice $gateway): array
    {
        return [
            'gw_id' => $gateway->gateway_id,
            'gateway_id' => $gateway->id,
            'network_id' => $gateway->network_station_id,
            'company_id' => $gateway->company_id,
            'lan_ip' => $gateway->lan_ip,
            'wifidog_port' => $gateway->wifidog_port,
            'status' => $gateway->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionContext(CaptiveSession $session): array
    {
        return [
            'captive_session_id' => $session->id,
            'session_status' => $session->status,
            'session_authenticated_at' => $session->authenticated_at?->toIso8601String(),
            'session_expires_at' => $session->expires_at?->toIso8601String(),
            'session_is_authenticated' => $session->isAuthenticated(),
            'session_client_mac' => $session->client_mac,
            'session_client_ip' => $session->client_ip,
            'session_gateway_address' => $session->gw_address,
            'session_gateway_port' => $session->gw_port,
            'session_network_device_id' => $session->network_device_id,
            'session_access_grant_id' => $session->access_grant_id,
            'session_network_session_id' => $session->network_session_id,
            'session_requested_url' => $session->requested_url,
            'session_token_hash' => hash('sha256', $session->token),
        ];
    }

    private function maskQueryValue(string $url, string $param): string
    {
        return (string) preg_replace('/([?&]'.preg_quote($param, '/').'=)[^&]*/i', '$1***', $url);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function trace(string $step, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->info('wifidog.'.$step, $context);
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
        return response('Auth: '.$code, 200)->header('Content-Type', 'text/plain');
    }
}
