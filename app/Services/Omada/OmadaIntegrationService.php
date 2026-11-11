<?php

namespace App\Services\Omada;

use App\Models\Company;
use App\Models\NetworkDevice;
use App\Models\OmadaAccessPoint;
use App\Models\OmadaIntegration;
use App\Models\OmadaSite;
use App\Models\OmadaSsid;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OmadaIntegrationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OmadaEndpointValidator $endpointValidator,
    ) {}

    /**
     * Create the Omada integration for an already-created router row.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForRouter(Company $company, NetworkDevice $router, array $data, User $actor): OmadaIntegration
    {
        $deploymentType = (string) ($data['omada_deployment_type'] ?? $data['deployment_type'] ?? OmadaIntegration::DEPLOYMENT_SOFTWARE);
        $connectionMethod = (string) ($data['connection_method'] ?? $data['omada_connection_method'] ?? OmadaIntegration::CONNECTION_PUBLIC);
        $verifyTls = filter_var($data['omada_verify_tls'] ?? $data['verify_tls'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! in_array($deploymentType, OmadaIntegration::DEPLOYMENT_TYPES, true)) {
            throw ValidationException::withMessages([
                'omada_deployment_type' => ['The Omada deployment type must be software, hardware, or cloud.'],
            ]);
        }

        $controllerUrl = (string) ($data['omada_controller_url'] ?? $data['controller_url'] ?? $data['api_host'] ?? '');
        $port = isset($data['omada_controller_port']) ? (int) $data['omada_controller_port'] : (isset($data['api_port']) ? (int) $data['api_port'] : null);
        $origin = $this->endpointValidator->validate($controllerUrl, $port, $verifyTls, $connectionMethod);

        $credentials = [
            'client_id' => (string) ($data['omada_client_id'] ?? $data['client_id'] ?? ''),
            'client_secret' => (string) ($data['omada_client_secret'] ?? $data['client_secret'] ?? ''),
            'operator_username' => (string) ($data['omada_operator_username'] ?? $data['operator_username'] ?? ''),
            'operator_password' => (string) ($data['omada_operator_password'] ?? $data['operator_password'] ?? ''),
        ];

        $parts = parse_url($origin);

        $integration = OmadaIntegration::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'name' => (string) ($data['omada_integration_name'] ?? $router->name),
            'deployment_type' => $deploymentType,
            'controller_url' => $origin,
            'controller_host' => (string) ($parts['host'] ?? ''),
            'controller_port' => (int) ($parts['port'] ?? config('omada.default_port', 8043)),
            'controller_id' => $data['omada_controller_id'] ?? $data['controller_id'] ?? null,
            'controller_version' => null,
            'connection_method' => $connectionMethod,
            'portal_key' => Str::random((int) config('omada.portal_key_length', 48)),
            'credentials' => $credentials,
            'verify_tls' => $verifyTls,
            'site_id' => $data['omada_site_id'] ?? $data['site_id'] ?? null,
            'site_name' => $data['omada_site_name'] ?? $data['site_name'] ?? null,
            'site_timezone' => $data['omada_site_timezone'] ?? $data['site_timezone'] ?? null,
            'connection_status' => OmadaIntegration::STATUS_UNKNOWN,
            'status' => 'active',
        ]);

        $this->applyControllerDefaults($router, $integration);

        $this->auditLogger->log('omada_integration_created', $actor, $company->id, OmadaIntegration::class, $integration->id);

        $this->probe($integration);

        $integration = $integration->fresh();
        $this->applyControllerDefaults($router, $integration);

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(OmadaIntegration $integration, array $data, User $actor): OmadaIntegration
    {
        $deploymentType = (string) ($data['omada_deployment_type'] ?? $data['deployment_type'] ?? $integration->deployment_type);
        $connectionMethod = (string) ($data['connection_method'] ?? $data['omada_connection_method'] ?? $integration->connection_method);

        if (! in_array($deploymentType, OmadaIntegration::DEPLOYMENT_TYPES, true)) {
            throw ValidationException::withMessages([
                'omada_deployment_type' => ['The Omada deployment type must be software, hardware, or cloud.'],
            ]);
        }

        $controllerUrl = (string) ($data['omada_controller_url'] ?? $data['controller_url'] ?? $integration->controller_url);
        $port = isset($data['omada_controller_port']) ? (int) $data['omada_controller_port'] : (isset($data['api_port']) ? (int) $data['api_port'] : $integration->controller_port);
        $verifyTls = filter_var($data['omada_verify_tls'] ?? $data['verify_tls'] ?? $integration->verify_tls, FILTER_VALIDATE_BOOLEAN);
        $origin = $this->endpointValidator->validate($controllerUrl, $port, $verifyTls, $connectionMethod);
        $parts = parse_url($origin);

        $credentials = $integration->credentials ?? [];
        foreach (['client_id', 'client_secret', 'operator_username', 'operator_password'] as $key) {
            $value = $data['omada_'.$key] ?? $data[$key] ?? null;

            if (filled($value)) {
                $credentials[$key] = (string) $value;
            }
        }

        $controllerChanged = $origin !== $integration->controller_url;

        $integration->fill([
            'name' => (string) ($data['omada_integration_name'] ?? $data['name'] ?? $integration->name),
            'deployment_type' => $deploymentType,
            'controller_url' => $origin,
            'controller_host' => (string) ($parts['host'] ?? ''),
            'controller_port' => (int) ($parts['port'] ?? config('omada.default_port', 8043)),
            'connection_method' => $connectionMethod,
            'credentials' => $credentials,
            'verify_tls' => $verifyTls,
            'controller_id' => $controllerChanged
                ? ($data['omada_controller_id'] ?? $data['controller_id'] ?? null)
                : ($data['omada_controller_id'] ?? $data['controller_id'] ?? $integration->controller_id),
            'controller_version' => $controllerChanged ? null : $integration->controller_version,
            'connection_status' => $controllerChanged ? OmadaIntegration::STATUS_UNKNOWN : $integration->connection_status,
        ])->save();

        if ($integration->networkDevice) {
            $this->applyControllerDefaults($integration->networkDevice, $integration);
        }

        $this->auditLogger->log('omada_integration_updated', $actor, $integration->company_id, OmadaIntegration::class, $integration->id);

        return $integration->fresh();
    }

    public function delete(OmadaIntegration $integration, User $actor): void
    {
        DB::transaction(function () use ($integration, $actor) {
            $this->auditLogger->log('omada_integration_deleted', $actor, $integration->company_id, OmadaIntegration::class, $integration->id);

            $router = $integration->networkDevice;
            $integration->delete();

            if ($router) {
                $router->delete();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function test(OmadaIntegration $integration): array
    {
        $started = microtime(true);

        try {
            $client = $this->client($integration);
            $info = $client->info();
            $controllerId = $client->controllerId();
            $version = $client->controllerVersion() ?? ($info['controllerVer'] ?? null);

            $sites = $client->paginated('/openapi/v1/'.$controllerId.'/sites');

            $latency = (int) round((microtime(true) - $started) * 1000);

            $integration->forceFill([
                'controller_id' => $controllerId,
                'controller_version' => $version,
                'connection_status' => OmadaIntegration::STATUS_CONNECTED,
                'last_connected_at' => now(),
            ])->save();

            $siteAccessible = null;
            if (filled($integration->site_id)) {
                $siteIds = array_map(fn (array $site) => (string) ($site['siteId'] ?? ''), $sites);
                $siteAccessible = in_array((string) $integration->site_id, $siteIds, true);
            }

            return [
                'gateway_type' => NetworkDevice::GATEWAY_OMADA,
                'reachable' => true,
                'authenticated' => true,
                'controller_id' => $controllerId,
                'controller_version' => $version,
                'api_version' => $info['apiVer'] ?? null,
                'sites_count' => count($sites),
                'site_accessible' => $siteAccessible,
                'latency_ms' => $latency,
                'message' => 'Connected to the Omada controller.',
            ];
        } catch (OmadaException $exception) {
            $status = in_array($exception->errorCode, [-44106, -44116, -1005], true)
                ? OmadaIntegration::STATUS_UNAUTHORIZED
                : OmadaIntegration::STATUS_UNREACHABLE;

            $integration->forceFill(['connection_status' => $status])->save();

            return [
                'gateway_type' => NetworkDevice::GATEWAY_OMADA,
                'reachable' => $exception->httpStatus !== null,
                'authenticated' => false,
                'controller_id' => $integration->controller_id,
                'controller_version' => $integration->controller_version,
                'sites_count' => 0,
                'site_accessible' => null,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => $exception->userMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function probe(OmadaIntegration $integration): array
    {
        $result = $this->test($integration);

        if ($result['reachable'] && $result['authenticated']) {
            try {
                $this->syncSites($integration);
            } catch (OmadaException) {
                // Discovery is best effort; the connection test already recorded the status.
            }
        }

        return $result;
    }

    /**
     * @return list<OmadaSite>
     */
    public function syncSites(OmadaIntegration $integration): array
    {
        $client = $this->client($integration);
        $sites = (new OmadaSiteService($client))->list();

        $siteIds = [];

        DB::transaction(function () use ($integration, $sites, &$siteIds) {
            foreach ($sites as $site) {
                $siteIds[] = $site['site_id'];

                OmadaSite::query()->updateOrCreate(
                    [
                        'omada_integration_id' => $integration->id,
                        'site_id' => $site['site_id'],
                    ],
                    [
                        'company_id' => $integration->company_id,
                        'site_name' => $site['name'],
                        'timezone' => $site['timezone'],
                        'status' => 'active',
                        'metadata' => $site['raw'],
                    ]
                );
            }

            $query = OmadaSite::query()->where('omada_integration_id', $integration->id);

            if ($siteIds !== []) {
                $query->whereNotIn('site_id', $siteIds);
            }

            $query->update(['status' => 'inactive']);
        });

        $this->touchSynced($integration);

        return OmadaSite::query()
            ->where('omada_integration_id', $integration->id)
            ->orderBy('site_name')
            ->get()
            ->all();
    }

    /**
     * @return list<OmadaAccessPoint>
     */
    public function syncAccessPoints(OmadaIntegration $integration): array
    {
        if (blank($integration->site_id)) {
            throw ValidationException::withMessages(['omada_site_id' => ['Select an Omada site before discovering access points.']]);
        }

        $client = $this->client($integration);
        $devices = (new OmadaDeviceService($client))->list((string) $integration->site_id);

        $deviceIds = [];

        DB::transaction(function () use ($integration, $devices, &$deviceIds) {
            foreach ($devices as $device) {
                if (blank($device['device_id'])) {
                    continue;
                }

                $deviceIds[] = $device['device_id'];

                OmadaAccessPoint::query()->updateOrCreate(
                    [
                        'omada_integration_id' => $integration->id,
                        'device_id' => $device['device_id'],
                    ],
                    [
                        'company_id' => $integration->company_id,
                        'site_id' => (string) $integration->site_id,
                        'device_name' => $device['device_name'],
                        'mac_address' => $device['mac_address'],
                        'model' => $device['model'],
                        'ip_address' => $device['ip_address'],
                        'firmware_version' => $device['firmware_version'],
                        'online_status' => $device['online_status'],
                        'metadata' => $device['raw'],
                    ]
                );
            }

            $query = OmadaAccessPoint::query()->where('omada_integration_id', $integration->id);

            if ($deviceIds !== []) {
                $query->whereNotIn('device_id', $deviceIds);
            }

            $query->update(['online_status' => 'offline']);
        });

        $this->touchSynced($integration);

        return OmadaAccessPoint::query()
            ->where('omada_integration_id', $integration->id)
            ->orderBy('device_name')
            ->get()
            ->all();
    }

    /**
     * @return list<OmadaSsid>
     */
    public function syncSsids(OmadaIntegration $integration): array
    {
        if (blank($integration->site_id)) {
            throw ValidationException::withMessages(['omada_site_id' => ['Select an Omada site before discovering SSIDs.']]);
        }

        $client = $this->client($integration);
        $ssids = (new OmadaWlanService($client))->list((string) $integration->site_id);

        $wlanIds = [];

        DB::transaction(function () use ($integration, $ssids, &$wlanIds) {
            foreach ($ssids as $ssid) {
                if (blank($ssid['wlan_id'])) {
                    continue;
                }

                $wlanIds[] = $ssid['wlan_id'];

                OmadaSsid::query()->updateOrCreate(
                    [
                        'omada_integration_id' => $integration->id,
                        'wlan_id' => $ssid['wlan_id'],
                    ],
                    [
                        'company_id' => $integration->company_id,
                        'site_id' => (string) $integration->site_id,
                        'ssid_name' => $ssid['ssid_name'],
                        'status' => $ssid['status'],
                        'portal_enabled' => $ssid['portal_enabled'],
                        'metadata' => $ssid['raw'],
                    ]
                );
            }

            $query = OmadaSsid::query()->where('omada_integration_id', $integration->id);

            if ($wlanIds !== []) {
                $query->whereNotIn('wlan_id', $wlanIds);
            }

            $query->update(['status' => 'inactive']);
        });

        $this->touchSynced($integration);

        return OmadaSsid::query()
            ->where('omada_integration_id', $integration->id)
            ->orderBy('ssid_name')
            ->get()
            ->all();
    }

    public function selectSite(OmadaIntegration $integration, string $siteId, User $actor): OmadaIntegration
    {
        $client = $this->client($integration);
        $site = (new OmadaSiteService($client))->find($siteId);

        if (! $site) {
            throw ValidationException::withMessages(['omada_site_id' => ['The selected site was not found on this Omada controller.']]);
        }

        $integration->forceFill([
            'site_id' => $site['site_id'],
            'site_name' => $site['name'],
            'site_timezone' => $site['timezone'],
        ])->save();

        OmadaSite::query()->updateOrCreate(
            ['omada_integration_id' => $integration->id, 'site_id' => $site['site_id']],
            [
                'company_id' => $integration->company_id,
                'site_name' => $site['name'],
                'timezone' => $site['timezone'],
                'status' => 'active',
                'metadata' => $site['raw'],
            ]
        );

        $this->auditLogger->log('omada_site_selected', $actor, $integration->company_id, OmadaIntegration::class, $integration->id, null, ['site_id' => $site['site_id']]);

        return $integration->fresh();
    }

    /**
     * @param  list<string>  $deviceIds
     */
    public function selectAccessPoints(OmadaIntegration $integration, array $deviceIds): void
    {
        DB::transaction(function () use ($integration, $deviceIds) {
            OmadaAccessPoint::query()
                ->where('omada_integration_id', $integration->id)
                ->update(['selected_for_waifai' => false]);

            if ($deviceIds !== []) {
                OmadaAccessPoint::query()
                    ->where('omada_integration_id', $integration->id)
                    ->whereIn('device_id', $deviceIds)
                    ->update(['selected_for_waifai' => true]);
            }
        });
    }

    public function selectSsid(OmadaIntegration $integration, ?string $wlanId): void
    {
        DB::transaction(function () use ($integration, $wlanId) {
            OmadaSsid::query()
                ->where('omada_integration_id', $integration->id)
                ->update(['selected_for_waifai' => false]);

            if (filled($wlanId)) {
                OmadaSsid::query()
                    ->where('omada_integration_id', $integration->id)
                    ->where('wlan_id', $wlanId)
                    ->update(['selected_for_waifai' => true]);
            }
        });
    }

    public function client(OmadaIntegration $integration): OmadaControllerClient
    {
        return new OmadaControllerClient($integration);
    }

    private function applyControllerDefaults(NetworkDevice $router, OmadaIntegration $integration): void
    {
        $router->forceFill([
            'gateway_type' => NetworkDevice::GATEWAY_OMADA,
            'api_host' => $integration->controller_host,
            'api_port' => $integration->controller_port,
            'api_username' => $integration->credential('client_id'),
            'api_verify_tls' => $integration->verify_tls,
            'connection_mode' => $integration->connection_method === OmadaIntegration::CONNECTION_VPN ? 'vpn' : 'public',
            'gateway_id' => $integration->controller_id ?: $router->gateway_id,
            'serial_number' => $integration->controller_id ?: $router->serial_number,
        ])->save();
    }

    private function touchSynced(OmadaIntegration $integration): void
    {
        $integration->forceFill(['last_synced_at' => now()])->save();
    }
}
