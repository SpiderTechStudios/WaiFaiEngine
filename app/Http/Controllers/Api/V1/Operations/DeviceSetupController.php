<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class DeviceSetupController extends Controller
{
    public function __construct(private SettingsService $settingsService) {}

    public function show(): JsonResponse
    {
        $company = $this->currentCompany();
        $settings = $this->settingsService->show($company);

        return $this->success([
            'portal_url' => $settings['portal_url'],
            'subdomain' => $company->subdomain,
            'methods' => [
                [
                    'key' => 'mikrotik',
                    'name' => 'MikroTik hotspot',
                    'steps' => [
                        'Connect to the MikroTik router and open Winbox or WebFig.',
                        'Create a hotspot server on the customer-facing interface.',
                        'Set the hotspot login page / walled garden to the portal URL below.',
                        'Allow DNS and the portal host in the walled garden so guests can reach the login page.',
                        'Use RADIUS or HTTP login callbacks if you want sessions to sync automatically.',
                    ],
                    'portal_url' => $settings['portal_url'],
                ],
                [
                    'key' => 'ruijie_cloud',
                    'name' => 'Ruijie Cloud',
                    'steps' => [
                        'Open Settings and save your Ruijie account ID and password.',
                        'In Ruijie Cloud, create or select the site for this business.',
                        'Enable the external captive portal / authentication URL.',
                        'Point the portal to the URL below using this company subdomain.',
                        'Confirm the AP/gateway is online, then add it under Routers.',
                    ],
                    'portal_url' => $settings['portal_url'],
                    'ruijie_account_configured' => $settings['ruijie_password_set'] || filled($settings['ruijie_account_id']),
                ],
            ],
        ], 'Device setup instructions');
    }
}
