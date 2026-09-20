<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Services\CaptiveSessionService;
use App\Services\GatewayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Testing-only WiFiDog controller for verifying the real Ruijie RAP62-OD flow.
 *
 * Test flow (payment is NOT part of this test):
 *   /api/wifidog/login          -> create session, redirect to the test portal
 *   /api/wifidog/portal         -> simple HTML test page (no redirect/authorize)
 *   /api/wifidog/portal/accept  -> authenticate the session, then redirect the
 *                                  browser to the gateway's /wifidog/auth and
 *                                  observe what Ruijie does next
 *   /api/wifidog/auth           -> diagnostic; returns "Auth: 1"
 *   /api/wifidog/ping           -> diagnostic; returns "Pong"
 */
class TestWifiDogController extends Controller
{
    public function __construct(
        private CaptiveSessionService $captiveSessionService,
        private GatewayResolver $gatewayResolver,
    ) {}

    /**
     * Step 1: Ruijie login.
     *
     * Resolve the gateway and the captive session, preserve the original URL,
     * then redirect the BROWSER to our test portal (/api/wifidog/portal) with
     * the session token and gateway/client context.
     *
     * We deliberately do NOT redirect to the gateway /wifidog/auth here and we
     * do NOT authorize the client.
     */
    public function login(Request $request)
    {
        Log::info('call from Login', ['request' => $request->all()]);

        $gwId = trim((string) $request->query('gw_id', ''));
        $gwSn = trim((string) $request->query('gw_sn', ''));
        $gwAddress = trim((string) $request->query('gw_address', ''));
        $gwPort = (int) ($request->query('gw_port') ?: 2060);
        $ip = trim((string) $request->query('ip', ''));
        $mac = trim((string) $request->query('mac', ''));
        $ssid = trim((string) $request->query('ssid', ''));
        $url = trim((string) $request->query('url', ''));

        $gateway = $this->gatewayResolver->resolveActive($gwId);

        $gwAddress = $gwAddress !== '' ? $gwAddress : (string) $gateway->lan_ip;
        $gwPort = (int) ($request->query('gw_port') ?: ($gateway->wifidog_port ?: 2060));

        $session = $this->captiveSessionService->resolveOrCreate($gateway, [
            'ip' => $ip !== '' ? $ip : null,
            'mac' => $mac !== '' ? $mac : null,
            'ssid' => $ssid !== '' ? $ssid : null,
            'gw_address' => $gwAddress !== '' ? $gwAddress : null,
            'gw_port' => $gwPort,
            'url' => $url !== '' ? $url : null,
        ]);

        $portalUrl = url('/api/wifidog/portal').'?'.http_build_query(array_filter([
            'token' => $session->token,
            'gw_id' => $gwId !== '' ? $gwId : $gateway->gateway_id,
            'gw_sn' => $gwSn !== '' ? $gwSn : null,
            'gw_address' => $gwAddress !== '' ? $gwAddress : null,
            'gw_port' => $gwPort,
            'ip' => $session->client_ip,
            'mac' => $session->client_mac,
            'ssid' => $session->ssid,
            'url' => $url !== '' ? $url : $session->requested_url,
        ], fn ($value) => $value !== null && $value !== ''));

        Log::info('simulator.login_redirect_portal', [
            'gw_id' => $gwId,
            'gw_address' => $gwAddress,
            'gw_port' => $gwPort,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'original_url' => $url !== '' ? $url : $session->requested_url,
            'session_token_hash' => hash('sha256', $session->token),
            'redirect_to' => preg_replace('/([?&]token=)[^&]+/i', '$1***', $portalUrl),
        ]);

        return redirect()->away($portalUrl);
    }

    /**
     * Step 2: the test captive portal.
     *
     * Simple HTML page only. No redirect to Google/original URL, no automatic
     * authorization, no message=denied. The page exposes an explicit
     * "ACCEPT TEST CLIENT" link to /api/wifidog/portal/accept.
     */
    public function portal(Request $request)
    {
        Log::info('WIFIDOG PORTAL', [
            'request' => $request->all(),
            'query' => $request->query(),
        ]);

        $token = trim((string) $request->query('token', ''));
        $message = trim((string) $request->query('message', ''));

        // Ruijie's message script (e.g. auth failure) also points here with
        // ?message=denied and no token. Render the gateway message instead of
        // failing with "token is required".
        if ($token === '' && $message !== '') {
            return $this->gatewayMessagePage($request, $message);
        }

        if ($token === '') {
            return response()->json([
                'ok' => false,
                'error' => 'token is required',
            ], 422);
        }

        $session = $this->captiveSessionService->findByToken($token);

        if (! $session) {
            return response()->json([
                'ok' => false,
                'error' => 'captive session not found',
            ], 404);
        }

        Log::info('simulator.portal_reached', [
            'captive_session_id' => $session->id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
        ]);

        $acceptUrl = e(url('/api/wifidog/portal/accept').'?'.http_build_query(['token' => $session->token]));
        $clientIp = e((string) $session->client_ip);
        $clientMac = e((string) $session->client_mac);
        $sessionId = e((string) $session->id);

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WaiFai Test Portal</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               background: #f1f5f9; color: #0f172a; margin: 0; padding: 24px; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 14px;
                padding: 22px; box-shadow: 0 1px 3px rgba(15,23,42,.08); }
        h1 { margin: 0 0 6px; font-size: 22px; }
        p { margin: 0 0 14px; color: #475569; }
        ul { margin: 0 0 18px; padding-left: 18px; color: #334155; font-size: 14px; }
        a.btn { display: block; text-align: center; background: #0F4C81; color: #fff;
                text-decoration: none; padding: 14px; border-radius: 10px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <h1>WaiFai Test Portal</h1>
        <p>Portal successfully reached.</p>
        <ul>
            <li>Client IP: {$clientIp}</li>
            <li>Client MAC: {$clientMac}</li>
            <li>Session ID: {$sessionId}</li>
        </ul>
        <a class="btn" href="{$acceptUrl}">ACCEPT TEST CLIENT</a>
    </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    /**
     * Renders the gateway's message script page (e.g. ?message=denied).
     */
    private function gatewayMessagePage(Request $request, string $message)
    {
        $gwId = (string) $request->query('gw_id', '');
        $clientIp = (string) $request->query('ip', '');
        $clientMac = (string) $request->query('mac', '');

        Log::info('WIFIDOG PORTAL MESSAGE', [
            'message' => $message,
            'gw_id' => $gwId,
            'client_mac' => $clientMac,
            'client_ip' => $clientIp,
        ]);

        $restartUrl = e(url('/api/wifidog/login').'?'.http_build_query(array_filter([
            'gw_id' => $gwId !== '' ? $gwId : null,
            'gw_sn' => $request->query('gw_sn'),
            'gw_address' => $request->query('gw_address'),
            'gw_port' => $request->query('gw_port'),
            'ip' => $clientIp !== '' ? $clientIp : null,
            'mac' => $clientMac !== '' ? $clientMac : null,
            'url' => $request->query('url'),
        ], fn ($value) => $value !== null && $value !== '')));

        $messageHtml = e($message);
        $gwIdHtml = e($gwId);
        $clientIpHtml = e($clientIp);
        $clientMacHtml = e($clientMac);

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WaiFai Test Portal</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               background: #f1f5f9; color: #0f172a; margin: 0; padding: 24px; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 14px;
                padding: 22px; box-shadow: 0 1px 3px rgba(15,23,42,.08); }
        h1 { margin: 0 0 6px; font-size: 22px; }
        .msg { display: inline-block; background: #fee2e2; color: #b91c1c; font-weight: 700;
               border-radius: 999px; padding: 3px 10px; font-size: 13px; }
        p { color: #475569; }
        ul { padding-left: 18px; color: #334155; font-size: 14px; }
        a.btn { display: block; text-align: center; background: #0F4C81; color: #fff;
                text-decoration: none; padding: 14px; border-radius: 10px; font-weight: 700; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>WaiFai Test Portal</h1>
        <p>Gateway message: <span class="msg">{$messageHtml}</span></p>
        <p>The Ruijie gateway redirected the browser here without a session token,
           which means it did not authorize the client &mdash; our
           <code>/api/wifidog/auth</code> was never called (no <code>call from Auth</code>
           in the server log).</p>
        <p>Most common causes:</p>
        <ul>
            <li>The AP's server&#8209;to&#8209;server <strong>AuthServer / Portal&nbsp;IP</strong> is not
                our public host, or the path is missing <code>/public</code>.</li>
            <li>The AP calls <code>/api/wifidog/auth</code> over <strong>HTTP (port 80)</strong> and the
                web server 301&#8209;redirects to HTTPS, which the WiFiDog client does not follow.</li>
        </ul>
        <ul>
            <li>Gateway ID: {$gwIdHtml}</li>
            <li>Client IP: {$clientIpHtml}</li>
            <li>Client MAC: {$clientMacHtml}</li>
        </ul>
        <a class="btn" href="{$restartUrl}">Restart test</a>
    </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    /**
     * Step 3: explicitly accept the test client.
     *
     * Authenticate the application captive session (test state only), then
     * redirect the browser to the gateway's local WiFiDog auth URL so we can
     * observe what Ruijie does next.
     */
    public function accept(Request $request)
    {
        Log::info('WIFIDOG PORTAL ACCEPT', [
            'request' => $request->all(),
            'query' => $request->query(),
        ]);

        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            return response()->json([
                'ok' => false,
                'error' => 'token is required',
            ], 422);
        }

        $session = $this->captiveSessionService->findByToken($token);

        if (! $session) {
            return response()->json([
                'ok' => false,
                'error' => 'captive session not found',
            ], 404);
        }

        // authenticate() needs a usable access grant (normally produced by the
        // payment workflow, which is out of scope for this test). Reuse the
        // session's grant when present, otherwise create a test-only grant.
        $grantId = $session->access_grant_id ?: $this->createTestAccessGrant($session)->id;

        $session = $this->captiveSessionService->authenticate($session, [
            'access_grant_id' => $grantId,
            'mac_address' => $session->client_mac,
            'ip_address' => $session->client_ip,
        ]);

        Log::info('WIFIDOG SESSION AUTHENTICATED', [
            'captive_session_id' => $session->id,
            'session_status' => $session->status,
            'access_grant_id' => $session->access_grant_id,
            'network_session_id' => $session->network_session_id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
        ]);

        $address = $session->gw_address ?: $session->networkDevice?->lan_ip;
        $port = $session->gw_port ?: ($session->networkDevice?->wifidog_port ?: 2060);

        $gatewayAuthUrl = "http://{$address}:{$port}/wifidog/auth?".http_build_query([
            'token' => $session->token,
        ]);

        Log::info('WIFIDOG PORTAL -> GATEWAY AUTH', [
            'gateway_address' => $address,
            'gateway_port' => $port,
            'captive_session_id' => $session->id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'redirect_to' => preg_replace('/([?&]token=)[^&]+/i', '$1***', $gatewayAuthUrl),
        ]);

        return redirect()->away($gatewayAuthUrl);
    }

    /**
     * Step 4: diagnostic auth endpoint.
     *
     * Returns exactly "Auth: 1" (text/plain). Does not modify gateway state.
     */
    public function auth(Request $request)
    {
        Log::info('call from Auth', [
            'request' => $request->all(),
            'query' => $request->query(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
        ]);

        return response('Auth: 1', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Step 5: diagnostic ping endpoint.
     */
    public function ping(Request $request)
    {
        Log::info('call from ping', ['request' => $request->all()]);

        return response('Pong', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Test-only access grant so CaptiveSessionService::authenticate() can run
     * without the payment workflow.
     */
    private function createTestAccessGrant(CaptiveSession $session): AccessGrant
    {
        $plan = InternetPlan::query()
            ->where('company_id', $session->company_id)
            ->where('status', 'active')
            ->orderBy('price')
            ->firstOrFail();

        $customer = Customer::query()->firstOrCreate(
            [
                'company_id' => $session->company_id,
                'phone' => 'SIM-'.preg_replace('/[^A-Fa-f0-9]/', '', (string) $session->client_mac),
            ],
            ['name' => 'Test Client', 'status' => 'active'],
        );

        return AccessGrant::query()->create([
            'company_id' => $session->company_id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'simulator',
            'starts_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);
    }

    /**
     * End-to-end guarantee test, following legacy/flow.png.
     *
     * Assumes payment is done and the user is meant to get internet. Walks:
     *   1) portal login      -> create/reuse the captive session
     *   2) payment (assumed) -> active access grant + authenticated session
     *   3) verify token (AP) -> CaptiveSessionService::authorizeToken() == Auth: 1
     *   4) portal result     -> redirect to the original URL
     *
     * internet_granted=true means the AP would run fw_allow() and the client
     * would show as online in Ruijie Cloud.
     */
    public function test(Request $request): JsonResponse
    {
        $steps = [];

        try {
            $gwId = trim((string) ($request->query('gw_id') ?: NetworkDevice::query()
                ->where('type', 'router')
                ->where('gateway_type', NetworkDevice::GATEWAY_RUIJIE)
                ->where('status', 'active')
                ->value('gateway_id')));

            $gateway = $this->gatewayResolver->resolveActive($gwId);

            $steps['1_gateway'] = [
                'gw_id' => $gateway->gateway_id,
                'gateway_id' => $gateway->id,
                'company_id' => $gateway->company_id,
                'lan_ip' => $gateway->lan_ip,
                'wifidog_port' => $gateway->wifidog_port,
            ];

            $mac = trim((string) ($request->query('mac') ?: 'AA:BB:CC:DD:EE:FF'));
            $ip = trim((string) ($request->query('ip') ?: '192.168.0.99'));
            $url = trim((string) ($request->query('url') ?: 'http://connectivitycheck.gstatic.com/generate_204'));

            // Diagram steps 3-5: portal login -> create/reuse the session.
            $session = $this->captiveSessionService->resolveOrCreate($gateway, [
                'mac' => $mac,
                'ip' => $ip,
                'ssid' => 'SIMULATOR',
                'gw_address' => $gateway->lan_ip,
                'gw_port' => $gateway->wifidog_port ?: 2060,
                'url' => $url,
            ]);

            $steps['2_login'] = [
                'captive_session_id' => $session->id,
                'status' => $session->status,
                'token' => $session->token,
                'client_mac' => $session->client_mac,
                'client_ip' => $session->client_ip,
            ];

            // Diagram step 6: payment success -> active grant + authenticated session.
            if (! $session->isAuthenticated()) {
                $plan = InternetPlan::query()
                    ->where('company_id', $gateway->company_id)
                    ->where('status', 'active')
                    ->orderBy('price')
                    ->firstOrFail();

                $customer = Customer::query()->firstOrCreate(
                    ['company_id' => $gateway->company_id, 'phone' => 'SIM-'.preg_replace('/[^A-Fa-f0-9]/', '', $mac)],
                    ['name' => 'Simulator User', 'status' => 'active'],
                );

                $grant = AccessGrant::query()->create([
                    'company_id' => $gateway->company_id,
                    'customer_id' => $customer->id,
                    'internet_plan_id' => $plan->id,
                    'source' => 'simulator',
                    'starts_at' => now(),
                    'expires_at' => now()->addHour(),
                    'status' => 'active',
                ]);

                $session = $this->captiveSessionService->authenticate($session, [
                    'access_grant_id' => $grant->id,
                    'mac_address' => $mac,
                    'ip_address' => $ip,
                ]);

                $steps['3_payment_assumed'] = [
                    'internet_plan_id' => $plan->id,
                    'plan' => $plan->name,
                    'access_grant_id' => $grant->id,
                    'network_session_id' => $session->network_session_id,
                    'session_status' => $session->status,
                ];
            } else {
                $steps['3_payment_assumed'] = ['skipped' => 'already authenticated'];
            }

            // Diagram steps 8-9: AP asks the portal to verify the token.
            $verified = $this->captiveSessionService->authorizeToken($session->token, $mac, $ip);

            $steps['4_verify_token'] = [
                'request' => '/api/wifidog/auth?stage=login&token=***&mac='.$mac.'&ip='.$ip,
                'response' => $verified ? 'Auth: 1' : 'Auth: 0',
            ];

            // Diagram steps 10-12: gateway lets the client online and redirects
            // it to the portal result page.
            $gatewayAuthUrl = $this->captiveSessionService->gatewayAuthRedirectUrl($session);
            $portalTarget = filled($session->requested_url)
                ? $session->requested_url
                : (string) config('captive.portal_success_url', 'http://www.google.com');

            $steps['5_gateway_auth_url'] = $gatewayAuthUrl;
            $steps['6_portal_result'] = $portalTarget;

            $internetGranted = $verified && $session->fresh()->isAuthenticated();

            return response()->json([
                'ok' => $internetGranted,
                'internet_granted' => $internetGranted,
                'online' => $internetGranted,
                'steps' => $steps,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'internet_granted' => false,
                'online' => false,
                'error' => $e->getMessage(),
                'steps' => $steps,
            ], 422);
        }
    }
}
