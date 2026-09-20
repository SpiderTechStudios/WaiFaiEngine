<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Services\CaptiveSessionService;
use App\Services\GatewayResolver;
use App\Services\WiFiDogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestWifiDogController extends Controller
{
    public function __construct(
        private CaptiveSessionService $captiveSessionService,
        private GatewayResolver $gatewayResolver,
        private WiFiDogService $wiFiDogService,
    ) {}

    /**
     * Portal login entry (Ruijie <ServiceURL>/login/).
     *
     * Creates/reuses the captive session and redirects the browser to the test
     * portal page, which shows the client context and an ACCEPT button.
     */
    public function login(Request $request)
    {
        Log::info('WIFIDOG LOGIN', [
            'request' => $request->all(),
        ]);

        $gwId = trim((string) $request->query('gw_id', ''));
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
            'gw_sn' => $request->query('gw_sn'),
            'gw_address' => $gwAddress !== '' ? $gwAddress : null,
            'gw_port' => $gwPort,
            'ip' => $session->client_ip,
            'mac' => $session->client_mac,
            'ssid' => $session->ssid,
            'url' => $url !== '' ? $url : $session->requested_url,
        ], fn ($value) => $value !== null && $value !== ''));

        Log::info('WIFIDOG LOGIN -> PORTAL', [
            'gw_id' => $gwId,
            'gw_address' => $gwAddress,
            'gw_port' => $gwPort,
            'captive_session_id' => $session->id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'redirect_to' => preg_replace('/([?&]token=)[^&]+/i', '$1***', $portalUrl),
        ]);

        return redirect()->away($portalUrl);
    }

    /**
     * Test captive portal page. Shows client context and an ACCEPT button
     * (/api/wifidog/portal/accept). When Ruijie sends ?message=... (no token),
     * show the gateway message page instead.
     */
    public function portal(Request $request)
    {
        Log::info('WIFIDOG PORTAL', [
            'request' => $request->all(),
        ]);

        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            if ($request->filled('message')) {
                return $this->gatewayMessagePage($request, (string) $request->query('message'));
            }

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

        Log::info('WIFIDOG PORTAL PAGE', [
            'captive_session_id' => $session->id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_status' => $session->status,
            'session_token_hash' => hash('sha256', $session->token),
        ]);

        $acceptUrl = e(url('/api/wifidog/portal/accept').'?'.http_build_query(['token' => $session->token]));
        $clientIp = e((string) $session->client_ip);
        $clientMac = e((string) $session->client_mac);
        $sessionId = e((string) $session->id);
        $status = e((string) $session->status);

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
            <li>Status: {$status}</li>
        </ul>
        <a class="btn" href="{$acceptUrl}">ACCEPT TEST CLIENT</a>
    </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    /**
     * The portal has "authenticated" the user: mark the app session authenticated
     * (test-only access grant if it has none) and redirect the browser to the
     * gateway's local WiFiDog auth URL, exactly as the Ruijie flow requires.
     */
    public function accept(Request $request)
    {
        Log::info('WIFIDOG PORTAL ACCEPT', [
            'request' => $request->all(),
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

        if (! $session->isAuthenticated()) {
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
        }

        $address = $session->gw_address ?: $session->networkDevice?->lan_ip;
        $port = $session->gw_port ?: ($session->networkDevice?->wifidog_port ?: 2060);

        $gatewayAuthUrl = $this->gatewayAuthUrl(
            (string) $address,
            (int) $port,
            $session->token,
            $session->requested_url,
        );

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
     * AP verifies the token with the portal (server-to-server). Delegates to the
     * real WiFiDog service so the test matches production exactly (all stages,
     * JSON for Ruijie APs / "Auth: N" for classic gateways).
     */
    public function auth(Request $request)
    {
        Log::info('WIFIDOG AUTH', [
            'request' => $request->all(),
        ]);

        return $this->wiFiDogService->auth($request);
    }

    public function ping(Request $request)
    {
        Log::info('WIFIDOG PING', [
            'request' => $request->all(),
        ]);

        return $this->wiFiDogService->ping($request);
    }

    /**
     * Test-only access grant so the app session can be authenticated without the
     * payment workflow.
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
     * Build the Ruijie gateway's local WiFiDog auth URL.
     */
    private function gatewayAuthUrl(string $address, int $port, string $token, ?string $url = null): string
    {
        $query = http_build_query(['token' => $token]);

        if (filled($url)) {
            $query .= '&url='.rawurlencode($url);
        }

        return "http://{$address}:{$port}/wifidog/auth?{$query}";
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
        <p>The gateway redirected the browser here without a session token, so our
           <code>/api/wifidog/auth</code> was not called with <code>stage=login</code>.</p>
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
}
