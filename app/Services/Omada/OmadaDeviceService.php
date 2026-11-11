<?php

namespace App\Services\Omada;

class OmadaDeviceService
{
    public function __construct(
        private readonly OmadaControllerClient $client,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $siteId): array
    {
        $rows = $this->client->paginated(
            '/openapi/v1/'.$this->client->controllerId().'/sites/'.rawurlencode($siteId).'/devices'
        );

        $devices = [];

        foreach ($rows as $row) {
            $type = strtolower((string) ($row['type'] ?? ''));

            if ($type !== '' && ! in_array($type, ['ap', 'eap'], true)) {
                continue;
            }

            $status = $this->onlineStatus($row);

            $devices[] = [
                'device_id' => (string) ($row['mac'] ?? $row['deviceId'] ?? ''),
                'device_name' => (string) ($row['name'] ?? $row['deviceName'] ?? ''),
                'mac_address' => $this->normalizeMac((string) ($row['mac'] ?? '')),
                'model' => isset($row['model']) ? (string) $row['model'] : null,
                'ip_address' => isset($row['ip']) ? (string) $row['ip'] : null,
                'firmware_version' => isset($row['firmwareVersion']) ? (string) $row['firmwareVersion'] : null,
                'online_status' => $status,
                'raw' => $row,
            ];
        }

        return $devices;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function onlineStatus(array $row): string
    {
        $category = $row['statusCategory'] ?? null;
        $status = $row['status'] ?? null;

        if (is_numeric($category)) {
            return (int) $category === 1 ? 'online' : 'offline';
        }

        if (is_numeric($status)) {
            return (int) $status === 1 ? 'online' : 'offline';
        }

        if (($row['adoptFailType'] ?? -1) !== -1) {
            return 'pending';
        }

        return 'unknown';
    }

    private function normalizeMac(string $mac): ?string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');

        return strlen($hex) === 12 ? implode('-', str_split($hex, 2)) : ($mac !== '' ? $mac : null);
    }
}
