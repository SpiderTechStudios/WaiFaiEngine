<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GatewayResolver
{
    /**
     * Resolve an active WiFiDog-capable gateway by gw_id / device id.
     *
     * @throws HttpException
     */
    public function resolveActive(string $gwId): NetworkDevice
    {
        $gwId = trim($gwId);

        if ($gwId === '') {
            throw new HttpException(400, 'gw_id is required.');
        }

        $gateway = $this->findByGatewayIdentifier($gwId);

        if (! $gateway) {
            Log::warning('wifidog.gateway_unknown', [
                'received_gw_id' => $gwId,
                'hint' => 'Register a Ruijie router whose gateway_id exactly matches this value (Routers API / admin).',
            ]);

            throw new HttpException(
                404,
                'Unknown WiFiDog gateway. No router is registered with gateway_id "'.$gwId.'".'
            );
        }

        if ($gateway->status !== 'active') {
            Log::info('wifidog.gateway_inactive', [
                'received_gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'status' => $gateway->status,
            ]);

            throw new HttpException(
                404,
                'WiFiDog gateway "'.$gwId.'" is registered but inactive (status: '.$gateway->status.').'
            );
        }

        if (! $this->supportsWiFiDog($gateway)) {
            Log::info('wifidog.gateway_not_enabled', [
                'received_gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'gateway_type' => $gateway->gateway_type,
            ]);

            throw new HttpException(
                404,
                'Router "'.$gwId.'" is not configured for WiFiDog (expected Ruijie/Wavlink with gateway_id).'
            );
        }

        $company = $gateway->company;
        if (! $company || $company->status !== 'active') {
            Log::info('wifidog.company_inactive', [
                'received_gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'company_id' => $gateway->company_id,
                'company_status' => $company?->status,
            ]);

            throw new HttpException(
                404,
                'WiFiDog gateway "'.$gwId.'" belongs to an inactive company.'
            );
        }

        if (blank($company->subdomain)) {
            Log::warning('wifidog.company_missing_subdomain', [
                'received_gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'company_id' => $company->id,
            ]);

            throw new HttpException(422, 'Captive portal subdomain is not configured for this network.');
        }

        return $gateway;
    }

    /**
     * Match WiFiDog gw_id / Ruijie device id against network_devices.gateway_id or serial_number.
     */
    public function findByGatewayIdentifier(string $gwId): ?NetworkDevice
    {
        $normalized = strtolower(trim($gwId));

        return NetworkDevice::query()
            ->with(['company', 'networkStation'])
            ->where('type', 'router')
            ->where(function ($query) use ($gwId, $normalized): void {
                $query->whereRaw('LOWER(gateway_id) = ?', [$normalized])
                    ->orWhereRaw('LOWER(serial_number) = ?', [$normalized])
                    ->orWhere('gateway_id', $gwId)
                    ->orWhere('serial_number', $gwId);
            })
            ->orderByDesc('id')
            ->first();
    }

    public function supportsWiFiDog(NetworkDevice $gateway): bool
    {
        if (blank($gateway->gateway_id) && blank($gateway->serial_number)) {
            return false;
        }

        return in_array($gateway->gateway_type, [
            NetworkDevice::GATEWAY_RUIJIE,
            NetworkDevice::GATEWAY_WAVLINK,
        ], true) || filled($gateway->wifidog_port);
    }

    public function companyFor(NetworkDevice $gateway): Company
    {
        $gateway->loadMissing('company');

        return $gateway->company;
    }
}
