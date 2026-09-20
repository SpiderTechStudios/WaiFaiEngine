<?php

namespace App\Services;

use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Production WiFiDog / Ruijie Reyee hotspot protocol.
 *
 * Flow (per the Ruijie Reyee WiFiDog Hotspot API article):
 *
 *   1. browser  -> GET  /api/wifidog/login   (AP redirects the unauthenticated client)
 *   2. server   -> portal page (/connect)    (NO authorization, NO gateway redirect)
 *   3. user authenticates through the existing production portal (voucher/payment)
 *   4. server   -> browser is sent to http://{gw_address}:{gw_port}/wifidog/auth?token=...
 *   5. AP       -> GET  /api/wifidog/auth   (server-to-server, stage=login)
 *   6. server   -> "Auth:1"                  (plain text)
 *   7. AP authorizes the client and sends the browser to /api/wifidog/portal
 *   8. server   -> success page / original destination
 *
 * The WiFiDog token is the existing captive-session token. It is minted together
 * with the *pending* session at step 1 but carries no authority: /auth only ever
 * returns "Auth:1" once the production portal has authenticated the session and a
 * valid access grant is attached.
 */
class WiFiDogService
{
    private const LOG_CHANNEL = 'wifidog';

    public function __construct(
        private GatewayResolver $gatewayResolver,
        private CaptiveSessionService $captiveSessionService,
    ) {}

    /**
     * Step 1/2 — gateway entry point for an unauthenticated client.
     *
     * Creates (or reuses) the pending captive session, stores the client/gateway
     * details + original URL, then sends the browser to the real production
     * captive portal. It NEVER authorizes the client and NEVER redirects to the
     * gateway auth endpoint.
     */
    public function login(Request $request): RedirectResponse
    {
        $this->trace('login', $this->requestContext($request, 'login'));

        $gateway = $this->resolveGatewayFromRequest($request);
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
     * Step 5/6 — gateway server-to-server authorization callback.
     *
     * Returns exactly "Auth:1" (authorized) or "Auth:0" (denied) as plain text.
     * Any stage: login (default), logout, counter/counters and query.
     */
    public function auth(Request $request): Response
    {
        $stage = strtolower(trim((string) $this->param($request, 'stage', 'login')));
        $stage = $stage === '' ? 'login' : $stage;

        $token = trim((string) $this->param($request, 'token', ''));
        $mac = filled($this->param($request, 'mac')) ? (string) $this->param($request, 'mac') : null;
        $ip = filled($this->param($request, 'ip')) ? (string) $this->param($request, 'ip') : null;

        $this->trace('auth', $this->requestContext($request, 'auth') + [
            'stage' => $stage,
            'incoming' => $this->param($request, 'incoming'),
            'outgoing' => $this->param($request, 'outgoing'),
        ]);

        $gateway = null;
        try {
            $gateway = $this->resolveGatewayFromRequest($request, required: false);
            if ($gateway) {
                $this->touchGateway($gateway, $request);
            }
        } catch (HttpException $e) {
            return $this->deny($stage, [
                'reason' => 'unknown_gateway',
                'request_gw_id' => $this->extractGatewayId($request),
                'request_gw_sn' => $this->param($request, 'gw_sn'),
                'message' => $e->getMessage(),
                'session_token_hash' => $token !== '' ? hash('sha256', $token) : null,
            ]);
        }

        // stage=query: Ruijie MAB / roaming status probe. Never trust the request
        // blindly — authorize only from the stored, authenticated session state.
        if ($stage === 'query') {
            $session = ($gateway && $mac)
                ? $this->captiveSessionService->findActiveByMac($gateway, $mac)
                : null;

            $allowed = $session !== null
                && $this->captiveSessionService->authorizeToken((string) $session->token, $mac, $ip);

            $this->trace('auth.query', [
                'direction' => 'api->ap',
                'stage' => $stage,
                'auth_code' => $allowed ? 1 : 0,
                'reason' => $allowed ? 'query_authenticated' : 'query_not_authenticated',
                'captive_session_id' => $session?->id,
                'client_mac' => $mac,
                'session_token_hash' => $session ? hash('sha256', $session->token) : null,
            ]);

            return $this->authResponse($allowed ? 1 : 0);
        }

        if ($token === '') {
            return $this->deny($stage, ['reason' => 'empty_token', 'client_mac' => $mac, 'client_ip' => $ip]);
        }

        $session = $this->captiveSessionService->findByToken($token);
        $tokenHash = hash('sha256', $token);

        if (! $session) {
            return $this->deny($stage, [
                'reason' => 'unknown_token',
                'client_mac' => $mac,
                'client_ip' => $ip,
                'session_token_hash' => $tokenHash,
            ]);
        }

        if ($gateway && (int) $session->network_device_id !== (int) $gateway->id) {
            return $this->deny($stage, [
                'reason' => 'gateway_token_mismatch',
                'request_gw_id' => $this->extractGatewayId($request),
                'session_gateway_id' => $session->network_device_id,
                'gateway_id' => $gateway->id,
                'session_token_hash' => $tokenHash,
            ]);
        }

        // stage=logout: deauthorize and preserve the historical session record.
        if ($stage === 'logout') {
            $this->captiveSessionService->disconnect($session);

            $this->trace('auth.logout', [
                'direction' => 'api->ap',
                'stage' => $stage,
                'auth_code' => 0,
                'reason' => 'logout',
                'captive_session_id' => $session->id,
                'client_mac' => $session->client_mac,
                'session_token_hash' => $tokenHash,
            ]);

            return $this->authResponse(0);
        }

        // stage=counter/counters: update usage then report whether the client
        // should stay authorized.
        if ($stage === 'counter' || $stage === 'counters') {
            $allowed = $this->captiveSessionService->authorizeToken($token, $mac, $ip);
            $this->recordCounters($session, $request);

            $this->trace('auth.counter', [
                'direction' => 'api->ap',
                'stage' => 'counter',
                'auth_code' => $allowed ? 1 : 0,
                'reason' => $allowed ? 'counter_authorized' : 'counter_denied',
                'captive_session_id' => $session->id,
                'client_mac' => $mac,
                'incoming' => $this->param($request, 'incoming'),
                'outgoing' => $this->param($request, 'outgoing'),
                'session_token_hash' => $tokenHash,
            ]);

            return $this->authResponse($allowed ? 1 : 0);
        }

        // stage=login (default): the gateway is verifying the token the portal
        // handed it after successful authentication.
        $allowed = $this->captiveSessionService->authorizeToken($token, $mac, $ip);

        if ($allowed) {
            $this->captiveSessionService->recordGatewayAuthorization($session, $ip);
        }

        $this->trace('auth.login', [
            'direction' => 'api->ap',
            'stage' => 'login',
            'auth_code' => $allowed ? 1 : 0,
            'reason' => $allowed ? 'authorized' : 'denied',
            'captive_session_id' => $session->id,
            'session_status' => $session->status,
            'client_mac' => $mac,
            'client_ip' => $ip,
            'session_token_hash' => $tokenHash,
        ]);

        return $this->authResponse($allowed ? 1 : 0);
    }

    /**
     * Step 7/8 — user-facing result page.
     *
     * - ?message=denied -> authentication failure page (never authorize, never
     *   redirect to the original Internet URL).
     * - authenticated session -> redirect to the original destination.
     * - otherwise -> the production captive portal (so the user can pay/redeem).
     */
    public function portal(Request $request): Response
    {
        $token = trim((string) $this->param($request, 'token', ''));
        $message = (string) $this->param($request, 'message', '');
        $mac = filled($this->param($request, 'mac')) ? (string) $this->param($request, 'mac') : null;

        $this->trace('portal', $this->requestContext($request, 'portal') + [
            'gateway_message' => $message !== '' ? $message : null,
        ]);

        $gateway = null;
        try {
            $gateway = $this->resolveGatewayFromRequest($request, required: false);
            if ($gateway) {
                $this->touchGateway($gateway, $request);
            }
        } catch (HttpException $e) {
            $this->trace('portal.gateway_unknown', [
                'request_gw_id' => $this->extractGatewayId($request),
                'request_gw_sn' => $this->param($request, 'gw_sn'),
                'message' => $e->getMessage(),
            ]);
        }

        $session = $token !== '' ? $this->captiveSessionService->findByToken($token) : null;

        if ($session && $gateway && (int) $session->network_device_id !== (int) $gateway->id) {
            $session = null;
        }

        // Gateway explicitly reported a failure (e.g. message=denied).
        if ($message !== '') {
            $this->trace('portal.out', [
                'direction' => 'api->mobile',
                'result' => 'gateway_failure_page',
                'gateway_message' => $message,
                'captive_session_id' => $session?->id,
                'session_token_hash' => $token !== '' ? hash('sha256', $token) : null,
                'response_status' => 200,
            ]);

            return $this->portalFailurePage($message, $session, $gateway);
        }

        // Ruijie success redirect is often /portal/?gw_id=&mac= without token.
        // Prefer the already-authenticated session over creating a new pending one.
        if (! $session && $gateway && $mac) {
            $session = $this->captiveSessionService->findActiveByMac($gateway, $mac);
        }

        if (! $session && $gateway && $message === '') {
            // Only create/reuse pending when this is a real portal entry, not a
            // post-auth success bounce without a known session.
            if ($token === '' && $mac === null) {
                return $this->portalFailurePage('denied', null, $gateway);
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
        }

        if ($session?->isAuthenticated()) {
            $target = $this->resolvePortalTargetUrl($session, $request->query('url'));

            $this->trace('portal.out', [
                'direction' => 'api->mobile',
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

        if (! $gateway || ! $session) {
            return $this->portalFailurePage('denied', $session, $gateway);
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
            'result' => 'redirect_captive_portal',
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

    /**
     * Gateway heartbeat. Must return exactly "Pong" as plain text.
     */
    public function ping(Request $request): Response
    {
        $this->trace('ping', $this->requestContext($request, 'ping'));

        try {
            $gateway = $this->resolveGatewayFromRequest($request);
            $this->touchGateway($gateway, $request);

            $this->trace('ping.out', [
                'direction' => 'api->ap',
                'reason' => 'ok',
                'gateway_id' => $gateway->id,
                'network_id' => $gateway->network_station_id,
                'gw_id' => $gateway->gateway_id,
                'dev_model' => $request->query('dev_model'),
                'dev_softversion' => $request->query('dev_softversion'),
                'sys_uptime' => $request->query('sys_uptime'),
                'sys_memfree' => $request->query('sys_memfree'),
                'sys_load' => $request->query('sys_load'),
                'wifidog_uptime' => $request->query('wifidog_uptime'),
                'user_agent' => $request->userAgent(),
                'response_status' => 200,
            ]);
        } catch (HttpException $e) {
            $this->trace('ping.out', [
                'direction' => 'api->ap',
                'reason' => 'gateway_rejected',
                'gw_id' => $this->extractGatewayId($request),
                'gw_sn' => $this->param($request, 'gw_sn'),
                'message' => $e->getMessage(),
                'response_status' => $e->getStatusCode(),
            ]);

            throw $e;
        }

        return response('Pong', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Plain-text WiFiDog auth response: exactly "Auth:1" or "Auth:0", HTTP 200.
     */
    private function authResponse(int $code): Response
    {
        return response('Auth:'.($code === 1 ? '1' : '0'), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function deny(string $stage, array $context): Response
    {
        $this->trace('auth.'.$stage, array_merge([
            'direction' => 'api->ap',
            'stage' => $stage,
            'auth_code' => 0,
        ], $context));

        return $this->authResponse(0);
    }

    /**
     * Persist WiFiDog usage counters onto the linked hotspot network session.
     * WiFiDog "incoming" is client -> network, "outgoing" is network -> client.
     */
    private function recordCounters(CaptiveSession $session, Request $request): void
    {
        if (! $session->network_session_id) {
            return;
        }

        $incoming = max(0, (int) $this->param($request, 'incoming', 0));
        $outgoing = max(0, (int) $this->param($request, 'outgoing', 0));

        NetworkSession::query()
            ->whereKey($session->network_session_id)
            ->update([
                'upload_bytes' => $incoming,
                'download_bytes' => $outgoing,
                'last_activity_at' => now(),
            ]);
    }

    private function portalFailurePage(string $message, ?CaptiveSession $session, ?NetworkDevice $gateway): Response
    {
        $title = 'Authentication failed';
        $reason = $message === 'denied'
            ? 'The gateway denied access for this device. Please try again or contact support.'
            : 'Your session could not be authorized: '.e($message);

        $retry = '';
        if ($session) {
            try {
                $retryUrl = $this->captiveSessionService->portalRedirectUrl($session);
                $retry = '<p><a href="'.e($retryUrl).'">Back to the portal</a></p>';
            } catch (\RuntimeException) {
                $retry = '';
            }
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.e($title).'</title></head><body style="font-family:system-ui,sans-serif;'
            .'max-width:32rem;margin:3rem auto;padding:0 1rem;text-align:center">'
            .'<h1>'.e($title).'</h1><p>'.$reason.'</p>'.$retry.'</body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Read a protocol parameter from either the query string or the POST body.
     */
    private function param(Request $request, string $key, mixed $default = null): mixed
    {
        $value = $request->input($key);

        return $value !== null && $value !== '' ? $value : $default;
    }

    private function caller(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        if (preg_match('/^(AP\s|Ruijie|Reyee|MCP|WMC)/i', $userAgent) || str_contains($userAgent, 'WiFiDog')) {
            return 'ap';
        }

        return 'mobile';
    }

    private function traceKey(Request $request): string
    {
        $mac = (string) $this->param($request, 'mac', '');
        $cleanMac = strtoupper((string) preg_replace('/[^A-Fa-f0-9]/', '', $mac));

        if ($cleanMac !== '') {
            return 'mac:'.$cleanMac;
        }

        $token = (string) $this->param($request, 'token', '');

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
        $token = (string) $this->param($request, 'token', '');

        return [
            'flow' => $flow,
            'direction' => $caller.'->api',
            'caller' => $caller,
            'trace_key' => $this->traceKey($request),
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'client_ip' => $this->param($request, 'ip') ?? $request->ip(),
            'client_mac' => $this->param($request, 'mac'),
            'gw_id' => $this->param($request, 'gw_id') ?: $this->param($request, 'dev_id'),
            'gw_sn' => $this->param($request, 'gw_sn'),
            'gw_address' => $this->param($request, 'gw_address'),
            'gw_port' => $this->param($request, 'gw_port'),
            'apmac' => $this->param($request, 'apmac'),
            'ssid' => $this->param($request, 'ssid'),
            'vlanid' => $this->param($request, 'vlanid'),
            'token_present' => $token !== '',
            'session_token_hash' => $token !== '' ? hash('sha256', $token) : null,
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
     * Structured production log lines: WIFIDOG LOGIN, WIFIDOG AUTH,
     * WIFIDOG AUTH LOGIN, WIFIDOG AUTH LOGOUT, WIFIDOG AUTH COUNTER,
     * WIFIDOG AUTH QUERY, WIFIDOG PORTAL, WIFIDOG PING.
     *
     * Tokens are never logged raw — callers pass session_token_hash.
     *
     * @param  array<string, mixed>  $context
     */
    private function trace(string $step, array $context = []): void
    {
        $message = 'WIFIDOG '.strtoupper(str_replace(['.', '_'], ' ', $step));

        Log::channel(self::LOG_CHANNEL)->info($message, $context);
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
     * Resolve the registered gateway from Ruijie identifiers.
     *
     * Tries gw_id, then dev_id, then gw_sn so either MAC or serial can match
     * network_devices.gateway_id / serial_number.
     */
    private function resolveGatewayFromRequest(Request $request, bool $required = true): ?NetworkDevice
    {
        $candidates = [];
        foreach (['gw_id', 'dev_id', 'gw_sn'] as $key) {
            $value = trim((string) $this->param($request, $key, ''));
            if ($value !== '' && ! in_array($value, $candidates, true)) {
                $candidates[] = $value;
            }
        }

        if ($candidates === []) {
            if ($required) {
                throw new HttpException(400, 'gw_id is required.');
            }

            return null;
        }

        $last = null;
        foreach ($candidates as $id) {
            try {
                return $this->gatewayResolver->resolveActive($id);
            } catch (HttpException $e) {
                $last = $e;
            }
        }

        // Identifiers were supplied but none matched a registered gateway.
        throw $last ?? new HttpException(404, 'Unknown WiFiDog gateway.');
    }

    /**
     * WiFiDog v1 sends gw_id; Ruijie also sends gw_sn (serial) and some builds
     * dev_id. Prefer the first non-empty for logging / single-id contexts.
     */
    private function extractGatewayId(Request $request): string
    {
        foreach (['gw_id', 'dev_id', 'gw_sn'] as $key) {
            $value = trim((string) $this->param($request, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
