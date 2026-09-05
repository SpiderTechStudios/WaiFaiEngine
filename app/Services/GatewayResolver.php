<?php

namespace App\Services;

use App\Models\Company;
use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GatewayResolver
{
    /**
     * Resolve an active WiFiDog-capable gateway by gw_id.
     *
     * @throws HttpException
     */
    public function resolveActive(string $gwId): NetworkDevice
    {
        $gwId = trim($gwId);

        if ($gwId === '') {
            throw new HttpException(400, 'gw_id is required.');
        }

        $gateway = NetworkDevice::query()
            ->with(['company', 'networkStation'])
            ->where('gateway_id', $gwId)
            ->whereNull('deleted_at')
            ->where('type', 'router')
            ->orderByDesc('id')
            ->first();

        if (! $gateway) {
            Log::info('wifidog.gateway_unknown', [
                'gw_id' => $gwId,
            ]);

            throw new HttpException(404, 'Unknown or inactive WiFiDog gateway');
        }

        if ($gateway->status !== 'active') {
            Log::info('wifidog.gateway_inactive', [
                'gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'status' => $gateway->status,
            ]);

            throw new HttpException(404, 'Unknown or inactive WiFiDog gateway');
        }

        if (! $this->supportsWiFiDog($gateway)) {
            Log::info('wifidog.gateway_not_enabled', [
                'gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'gateway_type' => $gateway->gateway_type,
            ]);

            throw new HttpException(404, 'Unknown or inactive WiFiDog gateway');
        }

        $company = $gateway->company;
        if (! $company || $company->status !== 'active') {
            Log::info('wifidog.company_inactive', [
                'gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'company_id' => $gateway->company_id,
            ]);

            throw new HttpException(404, 'Unknown or inactive WiFiDog gateway');
        }

        if (blank($company->subdomain)) {
            Log::warning('wifidog.company_missing_subdomain', [
                'gw_id' => $gwId,
                'gateway_id' => $gateway->id,
                'company_id' => $company->id,
            ]);

            throw new HttpException(422, 'Captive portal subdomain is not configured for this network.');
        }

        return $gateway;
    }

    public function supportsWiFiDog(NetworkDevice $gateway): bool
    {
        if (blank($gateway->gateway_id)) {
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
