<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkDevice;
use App\Models\User;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RouterService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private BranchService $branchService,
        private RuijieCloudService $ruijieCloudService,
        private MikroTikService $mikroTikService,
    ) {}

    public function create(Company $company, array $data, User $actor): NetworkDevice
    {
        $station = $this->branchService->defaultStation($company, $data['branch_id'] ?? null);
        $gatewayType = $data['gateway_type'];
        $isMikroTik = $gatewayType === NetworkDevice::GATEWAY_MIKROTIK;

        $router = NetworkDevice::query()->create([
            'company_id' => $company->id,
            'network_station_id' => $station->id,
            'type' => 'router',
            'gateway_type' => $gatewayType,
            'name' => $data['name'],
            'lan_ip' => $data['lan_ip'] ?? null,
            'api_host' => $isMikroTik ? ($data['api_host'] ?? null) : null,
            'api_port' => $isMikroTik
                ? (int) ($data['api_port'] ?? 443)
                : null,
            'api_username' => $isMikroTik ? ($data['api_username'] ?? null) : null,
            'api_password' => $isMikroTik && filled($data['api_password'] ?? null)
                ? Crypt::encryptString($data['api_password'])
                : null,
            'gateway_id' => match (true) {
                $isMikroTik => (string) ($data['gateway_id'] ?? $data['serial_number'] ?? 'mt-'.Str::lower(Str::random(10))),
                $gatewayType === NetworkDevice::GATEWAY_RUIJIE => $data['gateway_id'] ?? null,
                default => $data['gateway_id'] ?? null,
            },
            'serial_number' => in_array($gatewayType, [NetworkDevice::GATEWAY_RUIJIE, NetworkDevice::GATEWAY_MIKROTIK], true)
                ? ($data['serial_number'] ?? null)
                : null,
            'router_identity' => $isMikroTik ? ($data['router_identity'] ?? $data['serial_number'] ?? null) : null,
            'wifidog_port' => $gatewayType === NetworkDevice::GATEWAY_RUIJIE
                ? (int) ($data['wifidog_port'] ?? 2060)
                : null,
            'hotspot_port' => $isMikroTik
                ? (int) ($data['hotspot_port'] ?? config('mikrotik.hotspot_port', 80))
                : null,
            'portal_key' => $isMikroTik ? Str::random((int) config('mikrotik.portal_key_length', 48)) : null,
            'callback_secret' => $isMikroTik
                ? Crypt::encryptString(Str::random((int) config('mikrotik.callback_secret_length', 48)))
                : null,
            'api_verify_tls' => $isMikroTik ? (bool) ($data['api_verify_tls'] ?? true) : true,
            'connection_mode' => $isMikroTik ? ($data['connection_mode'] ?? 'public') : 'public',
            'status' => $data['status'] ?? 'active',
        ]);

        $this->auditLogger->log('router_created', $actor, $company->id, NetworkDevice::class, $router->id);

        return $router->load('networkStation.location');
    }

    public function update(NetworkDevice $router, array $data, User $actor): NetworkDevice
    {
        if (isset($data['branch_id'])) {
            $station = $this->branchService->defaultStation($router->company, $data['branch_id']);
            $data['network_station_id'] = $station->id;
            unset($data['branch_id']);
        }

        $gatewayType = $data['gateway_type'] ?? $router->gateway_type;
        $isMikroTik = $gatewayType === NetworkDevice::GATEWAY_MIKROTIK;

        if (array_key_exists('api_password', $data)) {
            $data['api_password'] = filled($data['api_password'])
                ? Crypt::encryptString($data['api_password'])
                : $router->api_password;
        }

        if ($isMikroTik && array_key_exists('api_port', $data) && blank($data['api_port'])) {
            $data['api_port'] = 443;
        }

        if ($isMikroTik && array_key_exists('hotspot_port', $data) && blank($data['hotspot_port'])) {
            $data['hotspot_port'] = config('mikrotik.hotspot_port', 80);
        } elseif ($isMikroTik && blank($router->hotspot_port)) {
            $data['hotspot_port'] = config('mikrotik.hotspot_port', 80);
        }

        if ($isMikroTik && blank($router->portal_key)) {
            $data['portal_key'] = Str::random((int) config('mikrotik.portal_key_length', 48));
        }

        if ($isMikroTik && blank($router->callback_secret)) {
            $data['callback_secret'] = Crypt::encryptString(Str::random((int) config('mikrotik.callback_secret_length', 48)));
        }

        if ($isMikroTik && blank($router->gateway_id)) {
            $data['gateway_id'] = 'mt-'.Str::lower(Str::random(10));
        }

        if ($gatewayType === NetworkDevice::GATEWAY_RUIJIE && array_key_exists('wifidog_port', $data) && blank($data['wifidog_port'])) {
            $data['wifidog_port'] = 2060;
        }

        $router->fill($data)->save();
        $this->auditLogger->log('router_updated', $actor, $router->company_id, NetworkDevice::class, $router->id);

        return $router->load('networkStation.location');
    }

    public function delete(NetworkDevice $router, User $actor): void
    {
        $this->auditLogger->log('router_deleted', $actor, $router->company_id, NetworkDevice::class, $router->id);
        $router->delete();
    }

    public function syncFromRuijie(NetworkDevice $router, User $actor): NetworkDevice
    {
        $router = $this->ruijieCloudService->syncRouter($router->load('company'));

        $this->auditLogger->log('router_synced', $actor, $router->company_id, NetworkDevice::class, $router->id);

        return $router->load('networkStation.location');
    }

    /**
     * Recent activity for a router: an ongoing offline event (derived from the
     * last heartbeat) plus the audit trail of changes and actions.
     *
     * @return list<array<string, mixed>>
     */
    public function events(NetworkDevice $router): array
    {
        $events = [];

        if ($router->last_seen_at && ! $router->isOnline()) {
            $events[] = [
                'type' => 'offline',
                'at' => $router->last_seen_at->toIso8601String(),
                'duration_seconds' => max(0, $router->last_seen_at->diffInSeconds(now())),
                'ongoing' => true,
                'description' => 'No heartbeat from the router since this time.',
                'by' => null,
            ];
        }

        AuditLog::query()
            ->with('user:id,first_name,last_name')
            ->where('entity_type', NetworkDevice::class)
            ->where('entity_id', $router->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->each(function (AuditLog $log) use (&$events): void {
                $events[] = [
                    'type' => match ($log->action) {
                        'router_created' => 'created',
                        'router_updated' => 'updated',
                        'router_synced' => 'synced',
                        'router_deleted' => 'deleted',
                        'router_rebooted' => 'rebooted',
                        default => $log->action,
                    },
                    'at' => $log->created_at?->toIso8601String(),
                    'duration_seconds' => null,
                    'ongoing' => false,
                    'description' => $this->describeEvent($log->action),
                    'by' => $log->user?->name,
                ];
            });

        usort($events, fn (array $a, array $b) => strcmp((string) $b['at'], (string) $a['at']));

        return $events;
    }

    /**
     * Connection test. Returns reachable true/false (no exception) for network
     * problems; configuration problems still abort 422.
     *
     * @return array<string, mixed>
     */
    public function test(NetworkDevice $router): array
    {
        if ($router->gateway_type === NetworkDevice::GATEWAY_RUIJIE) {
            return ['gateway_type' => $router->gateway_type] + $this->ruijieCloudService->testConnection($router);
        }

        if ($router->gateway_type === NetworkDevice::GATEWAY_MIKROTIK) {
            return $this->mikroTikService->test($router);
        }

        $host = $router->lan_ip;
        $port = 80;

        if (blank($host)) {
            abort(422, 'This router has no address to test.');
        }

        $started = microtime(true);
        $connection = @fsockopen((string) $host, $port, $errno, $errstr, 3);
        $latency = (int) round((microtime(true) - $started) * 1000);

        if ($connection === false) {
            return [
                'gateway_type' => $router->gateway_type,
                'reachable' => false,
                'latency_ms' => $latency,
                'host' => $host,
                'port' => $port,
                'message' => trim((string) ($errstr ?: 'Connection failed').' ('.$errno.')'),
            ];
        }

        fclose($connection);

        return [
            'gateway_type' => $router->gateway_type,
            'reachable' => true,
            'latency_ms' => $latency,
            'host' => $host,
            'port' => $port,
            'message' => 'Router address is reachable.',
        ];
    }

    /**
     * Reboot a MikroTik router through the RouterOS REST API.
     *
     * @return array<string, mixed>
     */
    public function reboot(NetworkDevice $router, User $actor): array
    {
        if ($router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            abort(422, 'Reboot is currently supported for MikroTik routers only.');
        }

        $host = $router->api_host ?: $router->lan_ip;

        if (blank($host) || blank($router->api_username) || blank($router->api_password)) {
            abort(422, 'MikroTik API host, username and password are required to reboot.');
        }

        try {
            $password = Crypt::decryptString($router->api_password);
        } catch (\Throwable) {
            abort(422, 'The stored MikroTik API password cannot be read. Update the router and try again.');
        }

        $port = (int) ($router->api_port ?: 443);
        $scheme = $port === 80 ? 'http' : 'https';
        $host = (string) preg_replace('#^https?://#i', '', (string) $host);

        try {
            $request = Http::withBasicAuth((string) $router->api_username, $password)
                ->timeout(10);

            if (! ($router->api_verify_tls ?? (bool) config('mikrotik.verify_tls', true))) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post("{$scheme}://{$host}:{$port}/rest/system/reboot");
        } catch (\Throwable $exception) {
            abort(502, 'Could not reach the router API: '.$exception->getMessage());
        }

        if ($response->failed()) {
            abort(502, 'The router API rejected the reboot (HTTP '.$response->status().').');
        }

        $this->auditLogger->log('router_rebooted', $actor, $router->company_id, NetworkDevice::class, $router->id);

        return [
            'requested' => true,
            'message' => 'Reboot command accepted by the router.',
        ];
    }

    /**
     * Connection guide for this router: step list plus the commands an operator
     * can paste. Values are real (portal URL, API host, gateway id, ports).
     *
     * @return array<string, mixed>
     */
    public function setup(NetworkDevice $router): array
    {
        $router->loadMissing('company');
        $company = $router->company;
        $serverHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'api.waifai.co.tz');
        $portalUrl = $company?->subdomain
            ? url('/connect?subdomain='.$company->subdomain)
            : url('/connect');

        $baseUrl = rtrim((string) config('app.url'), '/');
        $isMikroTik = $router->gateway_type === NetworkDevice::GATEWAY_MIKROTIK;
        $loginUrl = $isMikroTik
            ? $baseUrl.'/api/mikrotik/login?router='.$router->id
                .'&key='.($router->portal_key ?: '<portal-key>')
                .'&mac=$(mac)&ip=$(ip)&url=$(link-orig-esc)'
            : null;
        $loginTemplateUrl = $isMikroTik
            ? $baseUrl.'/api/mikrotik/login.html?router='.$router->id.'&key='.($router->portal_key ?: '')
            : null;
        $callbackSecret = null;

        if ($isMikroTik && filled($router->callback_secret)) {
            try {
                $callbackSecret = Crypt::decryptString((string) $router->callback_secret);
            } catch (\Throwable) {
                $callbackSecret = null;
            }
        }

        $walledGardenCommands = ['/ip hotspot walled-garden add dst-host='.$serverHost.' action=allow comment="WaiFai API"'];

        if (filter_var($serverHost, FILTER_VALIDATE_IP)) {
            $walledGardenCommands[] = '/ip hotspot walled-garden ip add action=accept dst-address='.$serverHost.' comment="WaiFai API"';
        }

        $steps = match ($router->gateway_type) {
            NetworkDevice::GATEWAY_RUIJIE => [
                [
                    'title' => 'Point the gateway to WaiFai',
                    'description' => 'In Ruijie Cloud open the network and set the Auth Server to this backend, then keep the AuthServer path as /api/wifidog/.',
                    'commands' => [
                        'Auth Server host: '.$serverHost,
                        'Auth Server port: 443',
                        'Auth Server path: /api/wifidog/',
                    ],
                ],
                [
                    'title' => 'Match the gateway identity',
                    'description' => 'The gateway ID and WiFiDog port below must match what is saved on this router.',
                    'commands' => [
                        'Gateway ID: '.($router->gateway_id ?: '—'),
                        'WiFiDog port: '.(string) ($router->wifidog_port ?: 2060),
                    ],
                ],
                [
                    'title' => 'Test the captive portal',
                    'description' => 'Connect a phone to the hotspot and confirm the portal opens at the URL below.',
                    'commands' => ['Portal URL: '.$portalUrl],
                ],
            ],
            NetworkDevice::GATEWAY_MIKROTIK => [
                [
                    'title' => 'Enable RouterOS REST',
                    'description' => 'Enable the REST service and create a dedicated API user restricted to the WaiFai egress IP.',
                    'commands' => [
                        '/ip service set www-ssl disabled=no port=443',
                        '/user group add name=waifai-rest policy=read,write,api,rest-api,test',
                        '/user add name=waifai-rest group=waifai-rest password=<strong-password> address=<backend-egress-ip>/32',
                    ],
                ],
                [
                    'title' => 'Run the hotspot wizard',
                    'description' => 'On the router open IP > Hotspot > Hotspot Setup and pick the LAN bridge that serves clients.',
                    'commands' => ['/ip hotspot setup'],
                ],
                [
                    'title' => 'Allow WaiFai through the walled garden',
                    'description' => 'The captive portal and payments must reach the backend before the client is authenticated.',
                    'commands' => $walledGardenCommands,
                ],
                [
                    'title' => 'Install the login page',
                    'description' => 'Replace the hotspot login page with the WaiFai redirect. Keep a backup first.',
                    'commands' => [
                        '/file copy hotspot/login.html hotspot/login.html.bak',
                        'Login URL: '.$loginUrl,
                        '/tool fetch url="'.($loginTemplateUrl ?: '').'" dst-path=hotspot/login.html mode=https check-certificate=yes',
                    ],
                ],
                [
                    'title' => 'Use HTTP PAP for the auto-login form',
                    'description' => 'The gateway page posts the one-time hotspot username/password to the router. Enable HTTP PAP on the hotspot profile.',
                    'commands' => [
                        '/ip hotspot profile set [find] login-by=http-pap',
                    ],
                ],
                [
                    'title' => 'Test the connection',
                    'description' => 'Run the connection test. It calls /rest/system/resource and /rest/ip/hotspot/active.',
                    'commands' => [
                        'POST /api/v1/routers/'.$router->id.'/test',
                        'Portal URL: '.$portalUrl,
                    ],
                ],
            ],
            default => [
                [
                    'title' => 'Set the hotspot portal',
                    'description' => 'Configure the device as an access point and set the captive portal redirect to the URL below.',
                    'commands' => ['Portal URL: '.$portalUrl],
                ],
                [
                    'title' => 'Allow the backend',
                    'description' => 'Make sure clients can reach the WaiFai API host before they are authenticated.',
                    'commands' => ['Allowed host: '.$serverHost],
                ],
            ],
        };

        return [
            'type' => $router->gateway_type,
            'portal_url' => $portalUrl,
            'server_host' => $serverHost,
            'hotspot_port' => $router->hotspot_port,
            'portal_key' => $router->portal_key,
            'login_url' => $loginUrl,
            'login_template_url' => $loginTemplateUrl,
            'login_html' => $isMikroTik && $loginUrl
                ? view('captive.mikrotik-login', ['loginUrl' => $loginUrl])->render()
                : null,
            'callback_url' => $isMikroTik ? $baseUrl.'/api/mikrotik/callback' : null,
            'callback_secret' => $callbackSecret,
            'steps' => $steps,
            'commands' => array_merge(...array_map(fn (array $step) => $step['commands'], $steps)),
        ];
    }

    private function describeEvent(string $action): string
    {
        return match ($action) {
            'router_created' => 'Router was added.',
            'router_updated' => 'Router settings were updated.',
            'router_synced' => 'Router was synced from Ruijie Cloud.',
            'router_deleted' => 'Router was removed.',
            'router_rebooted' => 'Reboot command was sent to the router.',
            default => 'Router activity: '.$action,
        };
    }
}
