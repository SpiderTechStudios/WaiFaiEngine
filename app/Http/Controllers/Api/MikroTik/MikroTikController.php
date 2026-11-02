<?php

namespace App\Http\Controllers\Api\MikroTik;

use App\Http\Controllers\Controller;
use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Services\CaptiveSessionService;
use App\Services\GatewayResolver;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class MikroTikController extends Controller
{
    public function __construct(
        private GatewayResolver $gatewayResolver,
        private CaptiveSessionService $captiveSessionService,
        private MikroTikService $mikroTikService,
    ) {}

    /**
     * Browser entry from the router's hotspot login.html.
     */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'router' => ['required', 'integer'],
            'key' => ['required', 'string', 'max:128'],
            'mac' => ['required', 'string', 'max:32'],
            'ip' => ['nullable', 'ip'],
            'ssid' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'subdomain' => ['nullable', 'string', 'max:255'],
        ]);

        $router = $this->gatewayResolver->resolveMikroTik((int) $data['router'], (string) $data['key']);

        if (filled($data['subdomain'] ?? null) && $data['subdomain'] !== $router->company->subdomain) {
            abort(404, 'Portal not found.');
        }

        $router->forceFill(['last_seen_at' => now()])->save();

        $session = $this->captiveSessionService->resolveOrCreate($router, [
            'ip' => $data['ip'] ?? null,
            'mac' => $data['mac'],
            'ssid' => $data['ssid'] ?? null,
            'gw_address' => $router->lan_ip,
            'gw_port' => (int) ($router->hotspot_port ?: config('mikrotik.hotspot_port', 80)),
            'url' => $data['url'] ?? null,
        ]);

        Log::channel('wifidog')->info('MIKROTIK LOGIN', [
            'router_id' => $router->id,
            'captive_session_id' => $session->id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
        ]);

        return redirect()->away($this->captiveSessionService->portalRedirectUrl($session));
    }

    /**
     * Optional router -> backend callback (on-login/on-logout/accounting).
     */
    public function callback(Request $request): Response
    {
        $router = $this->routerFromCallback($request);

        $event = strtolower((string) $request->input('event', 'accounting'));
        $username = trim((string) $request->input('user', ''));
        $mac = $this->mikroTikService->normalizeMac((string) ($request->input('mac') ?: $request->input('mac-address', '')));
        $activeId = trim((string) ($request->input('session_id') ?: $request->input('session-id', '')));

        if ($username === '' && $mac === null) {
            return response()->noContent();
        }

        $session = CaptiveSession::query()
            ->with(['accessGrant', 'networkSession'])
            ->where('network_device_id', $router->id)
            ->when($username !== '', fn ($query) => $query->where('mikrotik_username', $username))
            ->when($username === '' && $mac !== null, fn ($query) => $query->where('client_mac', $mac))
            ->latest('id')
            ->first();

        if (! $session) {
            return response()->noContent();
        }

        $session->forceFill([
            'router_active_id' => $activeId !== '' ? $activeId : $session->router_active_id,
            'router_authorized_at' => $session->router_authorized_at ?: now(),
            'router_last_seen_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $this->recordUsage($session, $request);

        if ($event === 'logout') {
            NetworkSession::query()
                ->whereKey($session->network_session_id)
                ->where('status', 'active')
                ->update(['status' => 'ended', 'ended_at' => now()]);

            if ($session->status !== CaptiveSession::STATUS_EXPIRED) {
                $session->forceFill(['status' => CaptiveSession::STATUS_DISCONNECTED])->save();
            }
        }

        return response()->noContent();
    }

    /**
     * Optional router heartbeat. Returns the same "Pong" contract as WiFiDog.
     */
    public function ping(Request $request): Response
    {
        $router = $this->routerFromCallback($request);
        $router->forceFill(['last_seen_at' => now()])->save();

        return response('Pong', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Serve the hotspot login.html body with real router values.
     */
    public function loginTemplate(Request $request): Response
    {
        $data = $request->validate([
            'router' => ['required', 'integer'],
            'key' => ['required', 'string', 'max:128'],
        ]);

        $router = $this->gatewayResolver->resolveMikroTik((int) $data['router'], (string) $data['key']);

        $loginUrl = rtrim((string) config('app.url'), '/')
            .'/api/mikrotik/login?router='.$router->id
            .'&key='.$router->portal_key
            .'&mac=$(mac)&ip=$(ip)&url=$(link-orig-esc)';

        return response()
            ->view('captive.mikrotik-login', ['loginUrl' => $loginUrl])
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store');
    }

    private function routerFromCallback(Request $request): NetworkDevice
    {
        $router = NetworkDevice::query()
            ->where('type', 'router')
            ->where('gateway_type', NetworkDevice::GATEWAY_MIKROTIK)
            ->findOrFail($request->integer('router'));

        $provided = (string) ($request->header('X-MikroTik-Secret') ?: $request->input('secret', ''));
        $expected = null;

        try {
            $expected = $router->callback_secret ? Crypt::decryptString((string) $router->callback_secret) : null;
        } catch (\Throwable) {
            $expected = null;
        }

        if ($expected === null || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid MikroTik callback secret.');
        }

        return $router;
    }

    private function recordUsage(CaptiveSession $session, Request $request): void
    {
        if (! $session->network_session_id) {
            return;
        }

        $updates = ['last_activity_at' => now()];

        if ($request->has('bytes-in') || $request->has('bytes_in')) {
            $updates['upload_bytes'] = max(0, (int) ($request->input('bytes-in') ?? $request->input('bytes_in')));
        }

        if ($request->has('bytes-out') || $request->has('bytes_out')) {
            $updates['download_bytes'] = max(0, (int) ($request->input('bytes-out') ?? $request->input('bytes_out')));
        }

        NetworkSession::query()
            ->whereKey($session->network_session_id)
            ->update($updates);
    }
}
