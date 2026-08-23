<?php

namespace App\Services;

use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class RuijieCloudService
{
    public function syncRouter(NetworkDevice $router): NetworkDevice
    {
        if ($router->gateway_type !== NetworkDevice::GATEWAY_RUIJIE) {
            abort(422, 'Only Ruijie routers can be synced from Ruijie Cloud.');
        }

        if (blank($router->gateway_id)) {
            abort(422, 'Router is missing a Ruijie gateway ID.');
        }

        $company = $router->company;

        if (blank($company->ruijie_account_id) || blank($company->ruijie_password)) {
            abort(422, 'Ruijie Cloud credentials are not configured for this company.');
        }

        $password = Crypt::decryptString($company->ruijie_password);
        $baseUrl = rtrim((string) config('services.ruijie.base_url'), '/');

        $response = Http::timeout((int) config('services.ruijie.timeout', 15))
            ->withBasicAuth($company->ruijie_account_id, $password)
            ->get("{$baseUrl}/devices/{$router->gateway_id}");

        if ($response->failed()) {
            abort(502, 'Unable to sync router from Ruijie Cloud.');
        }

        $payload = $response->json() ?? [];

        $router->fill([
            'serial_number' => $payload['serial_number'] ?? $router->serial_number,
            'status' => ($payload['online'] ?? false) ? 'active' : 'offline',
            'last_seen_at' => now(),
        ])->save();

        return $router->fresh();
    }
}
