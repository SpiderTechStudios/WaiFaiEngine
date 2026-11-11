<?php

namespace App\Services\Omada;

class OmadaSiteService
{
    public function __construct(
        private readonly OmadaControllerClient $client,
    ) {}

    /**
     * @return list<array{site_id: string, name: string, timezone: ?string, raw: array<string, mixed>}>
     */
    public function list(): array
    {
        $rows = $this->client->paginated('/openapi/v1/'.$this->client->controllerId().'/sites');

        $sites = [];

        foreach ($rows as $row) {
            $siteId = (string) ($row['siteId'] ?? $row['id'] ?? '');

            if ($siteId === '') {
                continue;
            }

            $sites[] = [
                'site_id' => $siteId,
                'name' => (string) ($row['name'] ?? $row['siteName'] ?? $siteId),
                'timezone' => isset($row['timeZone']) ? (string) $row['timeZone'] : (isset($row['timezone']) ? (string) $row['timezone'] : null),
                'raw' => $row,
            ];
        }

        return $sites;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $siteId): ?array
    {
        foreach ($this->list() as $site) {
            if ($site['site_id'] === $siteId) {
                return $site;
            }
        }

        return null;
    }
}
