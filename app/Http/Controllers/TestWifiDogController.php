<?php

namespace App\Http\Controllers;

use App\Services\CaptiveSessionService;
use App\Services\GatewayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestWifiDogController extends Controller
{
    public function __construct(
        private CaptiveSessionService $captiveSessionService,
        private GatewayResolver $gatewayResolver,
    ) {
    }

    /**
     * WiFiDog login:
     * Simulates an existing user who already paid.
     * Generates an authorized session and instantly sends them back to the router.
     */
    public function login(Request $request)
    {
        Log::info('WIFIDOG LOGIN [SIMULATION]', [
            'request' => $request->all(),
        ]);

        $gwId      = trim((string) $request->query('gw_id', ''));
        $gwAddress = trim((string) $request->query('gw_address', ''));
        $gwPort    = (int) ($request->query('gw_port') ?: 2060);
        $ip        = trim((string) $request->query('ip', ''));
        $mac       = trim((string) $request->query('mac', ''));
        $ssid      = trim((string) $request->query('ssid', ''));
        $url       = trim((string) $request->query('url', 'http://connectivitycheck.gstatic.com/generate_204'));

        $gateway   = $this->gatewayResolver->resolveActive($gwId);

        $gwAddress = $gwAddress !== '' ? $gwAddress : (string) $gateway->lan_ip;
        $gwPort    = (int) ($request->query('gw_port') ?: ($gateway->wifidog_port ?: 2060));

        // ---------------------------------------------------------------------
        // SIMULATION LOGIC: User has already paid / signed in
        // ---------------------------------------------------------------------
        // Resolve or create captive session
        $session = $this->captiveSessionService->resolveOrCreate($gateway, [
            'ip'         => $ip !== '' ? $ip : null,
            'mac'        => $mac !== '' ? $mac : null,
            'ssid'       => $ssid !== '' ? $ssid : null,
            'gw_address' => $gwAddress !== '' ? $gwAddress : null,
            'gw_port'    => $gwPort,
            'url'        => $url !== '' ? $url : null,
        ]);

        // Force mark session state as authorized / paid in database if service requires it
        if (method_exists($session, 'markAsPaid')) {
            $session->markAsPaid();
        }

        // Build gateway redirect URL
        $query = http_build_query(['token' => $session->token]);
        if ($url !== '') {
            $query .= '&url=' . rawurlencode($url);
        }

        $gatewayAuthUrl = "http://{$gwAddress}:{$gwPort}/wifidog/auth?{$query}";

        Log::info('WIFIDOG LOGIN -> REDIRECTING TO GATEWAY AUTH FOR INTERNET ACCESS', [
            'gw_id'              => $gwId,
            'gw_address'         => $gwAddress,
            'gw_port'            => $gwPort,
            'captive_session_id' => $session->id,
            'client_mac'         => $session->client_mac,
            'client_ip'          => $session->client_ip,
            'token'              => $session->token,
            'redirect_to'        => $gatewayAuthUrl,
        ]);

        // 302 Redirect user's phone directly to router gateway
        return redirect()->away($gatewayAuthUrl);
    }

    /**
     * WiFiDog Auth Endpoint:
     * Called directly by the Ruijie router in the background to validate the token.
     */
    public function auth(Request $request)
    {
        Log::info('WIFIDOG AUTH [REQUEST FROM ROUTER]', [
            'request' => $request->all(),
        ]);

        $token = $request->query('token');

        // Verify that token exists (or force true for pure simulation)
        if ($token) {
            Log::info('WIFIDOG AUTH -> ACCESS GRANTED (Auth: 1)');
            return response("Auth: 1", 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WIFIDOG AUTH -> ACCESS DENIED (Auth: 0)');
        return response("Auth: 0", 200)->header('Content-Type', 'text/plain');
    }

    /**
     * WiFiDog Portal Endpoint:
     * Called when authentication finishes.
     */
    public function portal(Request $request)
    {
        Log::info('WIFIDOG PORTAL', [
            'request' => $request->all(),
        ]);

        $message = $request->query('message');

        if ($message === 'success') {
            return response()->json([
                'status'  => 'success',
                'message' => 'Internet access granted successfully!'
            ], 200);
        }

        return response()->json([
            'status'  => 'failed',
            'message' => 'Access denied by gateway. Check server logs.'
        ], 400);
    }

    /**
     * WiFiDog Ping Endpoint:
     * Heartbeat check from the router.
     */
    public function ping(Request $request)
    {
        Log::info('WIFIDOG PING', [
            'request' => $request->all(),
        ]);

        return response("Pong", 200)->header('Content-Type', 'text/plain');
    }
}