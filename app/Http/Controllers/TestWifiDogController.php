<?php

namespace App\Http\Controllers;

use App\Services\CaptiveSessionService;
use App\Services\GatewayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TestWifiDogController extends Controller
{
    public function __construct(
        private CaptiveSessionService $captiveSessionService,
        private GatewayResolver $gatewayResolver,
    ) {
    }

    /**
     * WiFiDog login (browser entry point).
     *
     * Assumes the user is allowed to get internet, so instead of sending them
     * to the payment portal we resolve/create the captive session and go
     * straight to the gateway's auth endpoint:
     *
     *   302 -> http://{gw_address}:{gw_port}/wifidog/auth?token=$token&url=$url
     */
    public function login(Request $request)
    {
        Log::info('call from Login', ['request' => $request->all()]);

        $gwId = trim((string) $request->query('gw_id', ''));
        $gwAddress = trim((string) $request->query('gw_address', ''));
        $gwPort = (int) ($request->query('gw_port') ?: 2060);
        $url = trim((string) $request->query('url', ''));
        $mac = trim((string) $request->query('mac', ''));
        $ip = trim((string) $request->query('ip', ''));

        $token = null;

        if ($gwId !== '') {
            try {
                $gateway = $this->gatewayResolver->resolveActive($gwId);

                $gwAddress = $gwAddress !== '' ? $gwAddress : (string) $gateway->lan_ip;
                $gwPort = $request->query('gw_port') ? $gwPort : (int) ($gateway->wifidog_port ?: 2060);

                $session = $this->captiveSessionService->resolveOrCreate($gateway, [
                    'mac' => $mac !== '' ? $mac : null,
                    'ip' => $ip !== '' ? $ip : null,
                    'ssid' => (string) $request->query('ssid', ''),
                    'gw_address' => $gwAddress !== '' ? $gwAddress : null,
                    'gw_port' => $gwPort,
                    'url' => $url !== '' ? $url : null,
                ]);

                $token = $session->token;
            } catch (Throwable $e) {
                Log::warning('simulator.login_gateway_failed', [
                    'gw_id' => $gwId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // No registered gateway to resolve: still simulate a login by minting a
        // token so the gateway flow can be exercised end to end.
        if ($token === null) {
            $token = Str::lower(bin2hex(random_bytes(32)));
        }

        if ($gwAddress === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Missing gateway address (gw_address) and no gateway lan_ip available.',
                'token' => $token,
            ], 422);
        }

        $query = http_build_query(['token' => $token]);
        if ($url !== '') {
            $query .= '&url=' . rawurlencode($url);
        }

        $gatewayAuthUrl = "http://{$gwAddress}:{$gwPort}/wifidog/auth?{$query}";

        Log::info('simulator.login_redirect_gateway_auth', [
            'gw_id' => $gwId,
            'gw_address' => $gwAddress,
            'gw_port' => $gwPort,
            'session_token_hash' => hash('sha256', $token),
            'redirect_to' => preg_replace('/([?&]token=)[^&]+/i', '$1***', $gatewayAuthUrl),
        ]);

        return redirect()->away($gatewayAuthUrl);
    }

    /**
     * WiFiDog auth (gateway server-to-server call).
     *
     * Assumes the user is allowed, so always approve.
     */
    public function auth(Request $request)
    {
        Log::info('call from Auth', ['request' => $request->all()]);

        // return 'Auth: 1';
        return ['Auth' => 1];
    }

    /**
     * WiFiDog portal (browser, after the gateway authorizes the client).
     *
     * 302 the customer back to the URL they originally tried to open (stored on
     * the captive session), falling back to the configured success URL.
     */
    public function portal(Request $request)
    {
        Log::info('call from Portal', ['request' => $request->all()]);

        $target = trim((string) $request->query('url', ''));

        $token = trim((string) $request->query('token', ''));
        if ($token !== '') {
            $session = $this->captiveSessionService->findByToken($token);
            if ($session && filled($session->requested_url)) {
                $target = (string) $session->requested_url;
            }
        }

        if ($target === '' || !preg_match('#^https?://#i', $target)) {
            $target = (string) config('captive.portal_success_url', 'http://www.google.com');
        }

        Log::info('simulator.portal_redirect', ['target' => $target]);

        return redirect()->away($target);
    }

    public function ping(Request $request)
    {
        Log::info('call from ping', ['request' => $request->all()]);

        return 'Pong';
    }
}
