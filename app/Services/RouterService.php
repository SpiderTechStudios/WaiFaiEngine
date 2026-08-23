<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NetworkDevice;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class RouterService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private BranchService $branchService,
        private RuijieCloudService $ruijieCloudService,
    ) {}

    public function create(Company $company, array $data, User $actor): NetworkDevice
    {
        $station = $this->branchService->defaultStation($company, $data['branch_id'] ?? null);
        $gatewayType = $data['gateway_type'];

        $router = NetworkDevice::query()->create([
            'company_id' => $company->id,
            'network_station_id' => $station->id,
            'type' => 'router',
            'gateway_type' => $gatewayType,
            'name' => $data['name'],
            'lan_ip' => $data['lan_ip'] ?? null,
            'api_host' => $gatewayType === NetworkDevice::GATEWAY_MIKROTIK ? ($data['api_host'] ?? null) : null,
            'api_port' => $gatewayType === NetworkDevice::GATEWAY_MIKROTIK
                ? (int) ($data['api_port'] ?? 443)
                : null,
            'api_username' => $gatewayType === NetworkDevice::GATEWAY_MIKROTIK ? ($data['api_username'] ?? null) : null,
            'api_password' => $gatewayType === NetworkDevice::GATEWAY_MIKROTIK && filled($data['api_password'] ?? null)
                ? Crypt::encryptString($data['api_password'])
                : null,
            'gateway_id' => $gatewayType === NetworkDevice::GATEWAY_RUIJIE ? ($data['gateway_id'] ?? null) : null,
            'serial_number' => $gatewayType === NetworkDevice::GATEWAY_RUIJIE ? ($data['serial_number'] ?? null) : null,
            'wifidog_port' => $gatewayType === NetworkDevice::GATEWAY_RUIJIE
                ? (int) ($data['wifidog_port'] ?? 2060)
                : null,
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

        if (array_key_exists('api_password', $data)) {
            $data['api_password'] = filled($data['api_password'])
                ? Crypt::encryptString($data['api_password'])
                : $router->api_password;
        }

        if ($gatewayType === NetworkDevice::GATEWAY_MIKROTIK && array_key_exists('api_port', $data) && blank($data['api_port'])) {
            $data['api_port'] = 443;
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
}
