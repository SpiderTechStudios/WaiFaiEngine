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
    ) {}

    /**
     * WiFiDog login: resolve/create the captive session and redirect the browser
     * straight to the gateway's local auth URL.
     *
     *   302 -> http://{gw_address}:{gw_port}/wifidog/auth?token=...&url=...
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

        $query = http_build_query(['token' => $session->token]);
        if ($url !== '') {
            $query .= '&url='.rawurlencode($url);
        }

        $gwAddress = '192.168.0.1';
        $gatewayAuthUrl = "http://{$gwAddress}:{$gwPort}/wifidog/auth?{$query}";

        Log::info('WIFIDOG LOGIN -> GATEWAY AUTH', [
            'gw_id' => $gwId,
            'gw_address' => $gwAddress,
            'gw_port' => $gwPort,
            'captive_session_id' => $session->id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'redirect_to' => preg_replace('/([?&]token=)[^&]+/i', '$1***', $gatewayAuthUrl),
        ]);

        return redirect()->away($gatewayAuthUrl);
    }

    public function auth(Request $request)
    {
        Log::info('WIFIDOG AUTH', [
            'request' => $request->all(),
        ]);

        return 'Auth: 1';
    }

    public function portal(Request $request)
    {
        Log::info('WIFIDOG PORTAL', [
            'request' => request()->all(),
        ]);

        return 'Auth: 1';
    }

    public function ping(Request $request)
    {
        Log::info('WIFIDOG PING', [
            'request' => request()->all(),
        ]);

        return 'Pong';
    }
}
