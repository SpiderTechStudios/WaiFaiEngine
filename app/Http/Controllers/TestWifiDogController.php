<?php

namespace App\Http\Controllers;

use App\Models\AccessGrant;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Services\CaptiveSessionService;
use App\Services\GatewayResolver;
use Illuminate\Http\JsonResponse;
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

        return 'Auth: 1';
    }

    /**
     * WiFiDog portal (browser, after the gateway authorizes the client).
     *
     * 302 the customer back to the URL they originally tried to open (stored on
     * the captive session), falling back to the configured success URL.
     */
    public function portal(Request $request)
    {
        Log::info('call from Portal', [
            'request' => $request->all(),
        ]);

        $data = [
            'ok' => true,
            'message' => 'PORTAL REACHED',
            'request' => $request->all(),
        ];
        Log::info('simulator.portal_reached', $data);
        return response()->json($data);
    }

    public function ping(Request $request)
    {
        Log::info('call from ping', ['request' => $request->all()]);

        return 'Pong';
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
