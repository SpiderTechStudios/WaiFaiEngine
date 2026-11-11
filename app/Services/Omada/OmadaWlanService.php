<?php

namespace App\Services\Omada;

class OmadaWlanService
{
    public function __construct(
        private readonly OmadaControllerClient $client,
    ) {}

    /**
     * List SSIDs for a site. The Open API exposes WLAN groups under /wlans on
     * recent controllers and /setting/wlans on others; both shapes are
     * normalized here and the raw record is kept in metadata for version-
     * specific follow-up.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $siteId): array
    {
        $base = '/openapi/v1/'.$this->client->controllerId().'/sites/'.rawurlencode($siteId);

        try {
            $rows = $this->client->paginated($base.'/wlans');
        } catch (OmadaException $exception) {
            if (! in_array($exception->errorCode, [-1600, -1001], true) && $exception->httpStatus !== 404) {
                throw $exception;
            }

            $rows = $this->client->paginated($base.'/setting/wlans');
        }

        $ssids = [];

        foreach ($rows as $row) {
            $nested = $row['ssids'] ?? null;

            if (is_array($nested) && $nested !== []) {
                foreach ($nested as $ssid) {
                    if (! is_array($ssid)) {
                        continue;
                    }

                    $ssids[] = $this->normalize($ssid, $row);
                }

                continue;
            }

            $ssids[] = $this->normalize($row, $row);
        }

        return $ssids;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function normalize(array $row, array $group): array
    {
        $wlanId = (string) ($row['id'] ?? $row['ssidId'] ?? $row['wlanId'] ?? '');
        $name = (string) ($row['name'] ?? $row['ssidName'] ?? $row['ssid'] ?? $wlanId);

        return [
            'wlan_id' => $wlanId !== '' ? $wlanId : null,
            'ssid_name' => $name,
            'status' => ((bool) ($row['enable'] ?? $row['enabled'] ?? true)) ? 'active' : 'inactive',
            'portal_enabled' => (bool) ($row['portalEnable'] ?? $row['portalEnabled'] ?? $group['portalEnable'] ?? false),
            'raw' => [
                'ssid' => $row,
                'wlan_group' => $group,
            ],
        ];
    }
}
