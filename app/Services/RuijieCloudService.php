<?php

namespace App\Services;

use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RuijieCloudService
{
    public function syncRouter(NetworkDevice $router): NetworkDevice
    {
        $payload = $this->fetchDevice($router);

        $router->fill([
            'serial_number' => $payload['serial_number'] ?? $router->serial_number,
            'model' => $payload['model'] ?? $router->model,
            'firmware' => $payload['firmware'] ?? $payload['firmware_version'] ?? $router->firmware,
            'mac_address' => $this->normalizeMac($payload['mac'] ?? $payload['mac_address'] ?? null) ?? $router->mac_address,
            'status' => ($payload['online'] ?? false) ? 'active' : 'offline',
            'last_seen_at' => now(),
        ])->save();

        return $router->fresh();
    }

    /**
     * Read a device from Ruijie Cloud. Config problems abort 422; an unreachable
     * cloud aborts 502.
     *
     * @return array<string, mixed>
     */
    public function fetchDevice(NetworkDevice $router): array
    {
        $this->assertConfigured($router);

        $company = $router->company;
        $password = Crypt::decryptString($company->ruijie_password);
        $baseUrl = rtrim((string) config('services.ruijie.base_url'), '/');

        $response = Http::timeout((int) config('services.ruijie.timeout', 15))
            ->withBasicAuth($company->ruijie_account_id, $password)
            ->get("{$baseUrl}/devices/{$router->gateway_id}");

        if ($response->failed()) {
            abort(502, 'Unable to reach Ruijie Cloud.');
        }

        return $response->json() ?? [];
    }

    /**
     * Read-only connection test: 200 with reachable=true/false instead of aborting.
     *
     * @return array{reachable: bool, latency_ms: int, message: string, online: bool|null, serial_number: string|null, model: string|null, firmware: string|null}
     */
    public function testConnection(NetworkDevice $router): array
    {
        $this->assertConfigured($router);

        $started = microtime(true);

        try {
            $payload = $this->fetchDevice($router);
        } catch (HttpException $exception) {
            return [
                'reachable' => false,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => $exception->getMessage(),
                'online' => null,
                'serial_number' => null,
                'model' => null,
                'firmware' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'reachable' => false,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => 'Ruijie Cloud request failed: '.$exception->getMessage(),
                'online' => null,
                'serial_number' => null,
                'model' => null,
                'firmware' => null,
            ];
        }

        return [
            'reachable' => true,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'message' => 'Ruijie Cloud responded.',
            'online' => (bool) ($payload['online'] ?? false),
            'serial_number' => isset($payload['serial_number']) ? (string) $payload['serial_number'] : null,
            'model' => isset($payload['model']) ? (string) $payload['model'] : null,
            'firmware' => isset($payload['firmware'])
                ? (string) $payload['firmware']
                : (isset($payload['firmware_version']) ? (string) $payload['firmware_version'] : null),
        ];
    }

    private function assertConfigured(NetworkDevice $router): void
    {
        if ($router->gateway_type !== NetworkDevice::GATEWAY_RUIJIE) {
            abort(422, 'Only Ruijie routers can be synced from Ruijie Cloud.');
        }

        if (blank($router->gateway_id)) {
            abort(422, 'Router is missing a Ruijie gateway ID.');
        }

        $company = $router->company;

        if (blank($company?->ruijie_account_id) || blank($company->ruijie_password)) {
            abort(422, 'Ruijie Cloud credentials are not configured for this company.');
        }
    }

    private function normalizeMac(?string $mac): ?string
    {
        if ($mac === null || trim($mac) === '') {
            return null;
        }

        $clean = strtoupper(str_replace(['-', '.'], ':', trim($mac)));

        return mb_substr($clean, 0, 32);
    }
}
