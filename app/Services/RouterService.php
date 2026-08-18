<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NetworkDevice;
use App\Models\User;

class RouterService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private BranchService $branchService,
    ) {}

    public function create(Company $company, array $data, User $actor): NetworkDevice
    {
        $station = $this->branchService->defaultStation($company, $data['branch_id'] ?? null);

        $router = NetworkDevice::query()->create([
            'company_id' => $company->id,
            'network_station_id' => $station->id,
            'type' => 'router',
            'vendor' => $data['vendor'] ?? null,
            'model' => $data['model'] ?? null,
            'name' => $data['name'],
            'serial_number' => $data['serial_number'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'status' => $data['status'] ?? 'active',
            'metadata' => $data['metadata'] ?? null,
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

        $router->fill($data)->save();
        $this->auditLogger->log('router_updated', $actor, $router->company_id, NetworkDevice::class, $router->id);

        return $router->load('networkStation.location');
    }

    public function delete(NetworkDevice $router, User $actor): void
    {
        $this->auditLogger->log('router_deleted', $actor, $router->company_id, NetworkDevice::class, $router->id);
        $router->delete();
    }
}
