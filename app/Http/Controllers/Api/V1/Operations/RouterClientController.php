<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Services\AuditLogger;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouterClientController extends Controller
{
    public function __construct(
        private MikroTikService $mikroTikService,
        private AuditLogger $auditLogger,
    ) {}

    public function index(NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router);

        if ($router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            return $this->error([], 'Client management is currently supported for MikroTik routers only.', 422);
        }

        try {
            $active = $this->mikroTikService->activeClients($router);
        } catch (\Throwable $exception) {
            return $this->error([], $exception->getMessage(), 502);
        }

        $usernames = array_values(array_filter(array_map(
            fn (array $row) => (string) ($row['user'] ?? ''),
            $active,
        )));
        $macs = array_values(array_filter(array_map(
            fn (array $row) => $this->mikroTikService->normalizeMac((string) ($row['mac-address'] ?? '')),
            $active,
        )));

        $sessions = CaptiveSession::query()
            ->where('network_device_id', $router->id)
            ->where(function ($query) use ($usernames, $macs): void {
                if ($usernames !== []) {
                    $query->whereIn('mikrotik_username', $usernames);
                }

                if ($macs !== []) {
                    $usernames === []
                        ? $query->whereIn('client_mac', $macs)
                        : $query->orWhereIn('client_mac', $macs);
                }
            })
            ->get();

        $byUsername = $sessions->keyBy('mikrotik_username');
        $byMac = $sessions->keyBy('client_mac');

        $clients = array_map(function (array $row) use ($byUsername, $byMac): array {
            $mac = $this->mikroTikService->normalizeMac((string) ($row['mac-address'] ?? ''));
            $session = ($row['user'] ?? null)
                ? $byUsername->get((string) $row['user'])
                : null;
            $session ??= $mac !== null ? $byMac->get($mac) : null;

            return [
                'id' => $row['.id'] ?? null,
                'user' => $row['user'] ?? null,
                'mac' => $mac,
                'ip' => $row['address'] ?? null,
                'uptime' => $row['uptime'] ?? null,
                'bytes_in' => (int) ($row['bytes-in'] ?? 0),
                'bytes_out' => (int) ($row['bytes-out'] ?? 0),
                'comment' => $row['comment'] ?? null,
                'captive_session_id' => $session?->id,
                'network_session_id' => $session?->network_session_id,
            ];
        }, $active);

        return $this->success($clients, 'Router clients retrieved');
    }

    public function destroy(Request $request, NetworkDevice $router, string $mac): JsonResponse
    {
        $this->assertCompany($router);

        if ($router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            return $this->error([], 'Client management is currently supported for MikroTik routers only.', 422);
        }

        $normalized = $this->mikroTikService->normalizeMac($mac);

        if ($normalized === null) {
            return $this->error(['mac' => ['Invalid MAC address.']], 'Validation Error', 422);
        }

        $sessions = CaptiveSession::query()
            ->where('network_device_id', $router->id)
            ->where('client_mac', $normalized)
            ->get();

        $usernames = $sessions->pluck('mikrotik_username')->filter()->values()->all();

        try {
            $removed = $this->mikroTikService->removeByMac($router, $normalized, $usernames);
        } catch (\Throwable $exception) {
            return $this->error([], $exception->getMessage(), 502);
        }

        foreach ($sessions as $session) {
            if ($session->network_session_id) {
                NetworkSession::query()
                    ->whereKey($session->network_session_id)
                    ->where('status', 'active')
                    ->update(['status' => 'ended', 'ended_at' => now()]);
            }

            if ($session->status !== CaptiveSession::STATUS_EXPIRED) {
                $session->forceFill([
                    'status' => CaptiveSession::STATUS_DISCONNECTED,
                    'mikrotik_username' => null,
                    'mikrotik_password' => null,
                    'mikrotik_user_id' => null,
                    'mikrotik_grant_id' => null,
                    'mikrotik_provisioned_at' => null,
                    'router_active_id' => null,
                ])->save();
            } else {
                $session->forceFill([
                    'mikrotik_username' => null,
                    'mikrotik_password' => null,
                    'mikrotik_user_id' => null,
                    'mikrotik_grant_id' => null,
                    'mikrotik_provisioned_at' => null,
                    'router_active_id' => null,
                ])->save();
            }
        }

        $this->auditLogger->log(
            'router_client_removed',
            $request->user(),
            $router->company_id,
            NetworkDevice::class,
            $router->id,
            newValues: [
                'mac' => $normalized,
                'removed' => $removed,
            ],
        );

        return $this->success($removed, 'Router client removed');
    }

    private function assertCompany(NetworkDevice $router): void
    {
        if ($router->type !== 'router' || (int) $router->company_id !== (int) $this->currentCompany()->id) {
            abort(404);
        }
    }
}
