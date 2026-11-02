<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Services\CaptiveSessionService;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MikroTikGatewayController extends Controller
{
    public function __construct(
        private CaptiveSessionService $captiveSessionService,
        private MikroTikService $mikroTikService,
    ) {}

    public function show(Request $request, string $token): View|RedirectResponse|Response
    {
        $session = $this->captiveSessionService->findByToken($token);

        if (! $session) {
            abort(404, 'Captive session not found.');
        }

        $session->loadMissing(['company', 'networkDevice', 'accessGrant']);

        if ($session->company?->status !== 'active') {
            abort(404, 'Captive session not found.');
        }

        if ($session->markExpiredIfNeeded()) {
            abort(410, 'This captive session has expired.');
        }

        if (! $session->isAuthenticated()) {
            return redirect()->away($this->portalUrl($session));
        }

        $router = $session->networkDevice;

        if (! $router || $router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            abort(404, 'Captive session not found.');
        }

        $grant = $session->accessGrant;

        if (! $grant || ! $grant->isUsable()) {
            return response()
                ->view('captive.mikrotik-gateway-error', [
                    'message' => 'This access grant has expired or is no longer active.',
                    'retryUrl' => $request->fullUrl(),
                    'portalUrl' => $this->portalUrl($session),
                ], 410)
                ->header('Cache-Control', 'no-store');
        }

        try {
            $this->mikroTikService->ensureProvisioned($session);
        } catch (\Throwable $exception) {
            Log::warning('MikroTik gateway provisioning failed', [
                'captive_session_id' => $session->id,
                'router_id' => $router->id,
                'error' => $exception->getMessage(),
            ]);

            return response()
                ->view('captive.mikrotik-gateway-error', [
                    'message' => 'We could not prepare your internet access. Please try again.',
                    'retryUrl' => $request->fullUrl(),
                    'portalUrl' => $this->portalUrl($session),
                ], 503)
                ->header('Cache-Control', 'no-store');
        }

        $session->refresh()->loadMissing(['networkDevice', 'accessGrant']);

        $target = $this->targetUrl($session);

        if ($this->isActiveOnRouter($router, $session)) {
            return redirect()->away($target);
        }

        $gatewayAddress = $session->gw_address ?: $router->lan_ip;
        $gatewayPort = (int) ($session->gw_port ?: ($router->hotspot_port ?: config('mikrotik.hotspot_port', 80)));

        if (blank($gatewayAddress) || blank($session->mikrotik_username) || blank($session->mikrotik_password)) {
            return response()
                ->view('captive.mikrotik-gateway-error', [
                    'message' => 'This router is missing its hotspot address or login details.',
                    'retryUrl' => $request->fullUrl(),
                    'portalUrl' => $this->portalUrl($session),
                ], 422)
                ->header('Cache-Control', 'no-store');
        }

        try {
            $password = Crypt::decryptString((string) $session->mikrotik_password);
        } catch (\Throwable) {
            return response()
                ->view('captive.mikrotik-gateway-error', [
                    'message' => 'Your internet access could not be prepared. Please start again from the portal.',
                    'retryUrl' => $request->fullUrl(),
                    'portalUrl' => $this->portalUrl($session),
                ], 500)
                ->header('Cache-Control', 'no-store');
        }

        return response()
            ->view('captive.mikrotik-gateway', [
                'gatewayAddress' => $gatewayAddress,
                'gatewayPort' => $gatewayPort,
                'gatewayScheme' => (string) config('mikrotik.hotspot_scheme', 'http'),
                'username' => $session->mikrotik_username,
                'password' => $password,
                'target' => $target,
                'portalUrl' => $this->portalUrl($session),
            ])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    private function isActiveOnRouter(NetworkDevice $router, CaptiveSession $session): bool
    {
        $mac = $this->mikroTikService->normalizeMac($session->client_mac);

        if ($mac === null) {
            return false;
        }

        try {
            foreach ($this->mikroTikService->activeClients($router) as $row) {
                if ($this->mikroTikService->normalizeMac((string) ($row['mac-address'] ?? '')) === $mac) {
                    return true;
                }
            }
        } catch (\Throwable $exception) {
            Log::debug('MikroTik active-client check skipped', [
                'router_id' => $router->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    private function targetUrl(CaptiveSession $session): string
    {
        $candidates = array_filter([
            (string) ($session->requested_url ?? ''),
            (string) config('captive.portal_success_url', 'http://www.google.com'),
        ]);

        foreach ($candidates as $candidate) {
            $parts = parse_url($candidate);

            if (isset($parts['scheme'], $parts['host']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                return $candidate;
            }
        }

        return 'http://www.google.com';
    }

    private function portalUrl(CaptiveSession $session): string
    {
        try {
            return $this->captiveSessionService->portalRedirectUrl($session);
        } catch (\Throwable) {
            return url('/connect?subdomain='.$session->company?->subdomain);
        }
    }
}
