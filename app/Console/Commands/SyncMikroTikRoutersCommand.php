<?php

namespace App\Console\Commands;

use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncMikroTikRoutersCommand extends Command
{
    protected $signature = 'mikrotik:sync {--router= : Sync a single router ID}';

    protected $description = 'Sync MikroTik router status, active clients, usage, and stale sessions';

    public function handle(MikroTikService $mikroTikService): int
    {
        $query = NetworkDevice::query()
            ->where('type', 'router')
            ->where('gateway_type', NetworkDevice::GATEWAY_MIKROTIK);

        if ($this->option('router')) {
            $query->whereKey((int) $this->option('router'));
        }

        $routers = $query->orderBy('id')->get();

        if ($routers->isEmpty()) {
            $this->info('No MikroTik routers to sync.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($routers as $router) {
            try {
                $result = $mikroTikService->syncRouter($router);
                $this->syncRouter($router, $result, $mikroTikService);
                $synced++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn('Router '.$router->id.' ('.$router->name.') sync failed: '.$exception->getMessage());
            }
        }

        $this->info(sprintf('Synced %d MikroTik router(s), %d failed.', $synced, $failed));

        return self::SUCCESS;
    }

    /**
     * @param  array{resource: array<string, mixed>, active: list<array<string, mixed>>}  $result
     */
    private function syncRouter(NetworkDevice $router, array $result, MikroTikService $mikroTikService): void
    {
        $resource = $result['resource'];
        $active = $result['active'];

        $router->forceFill([
            'last_seen_at' => now(),
            'last_synced_at' => now(),
            'status' => 'active',
            'firmware' => isset($resource['version']) ? (string) $resource['version'] : $router->firmware,
        ])->save();

        $sessions = CaptiveSession::query()
            ->where('network_device_id', $router->id)
            ->latest('id')
            ->get();

        $byUsername = $sessions
            ->filter(fn (CaptiveSession $session) => filled($session->mikrotik_username))
            ->keyBy('mikrotik_username');
        $byMac = $sessions
            ->filter(fn (CaptiveSession $session) => filled($session->client_mac))
            ->keyBy('client_mac');

        $seen = [];

        foreach ($active as $row) {
            $username = (string) ($row['user'] ?? '');
            $mac = $this->normalizeMac((string) ($row['mac-address'] ?? ''));
            $session = $username !== '' ? $byUsername->get($username) : null;
            $session ??= $mac !== null ? $byMac->get($mac) : null;

            if (! $session) {
                continue;
            }

            $seen[$session->id] = true;

            $session->forceFill([
                'router_active_id' => isset($row['.id']) ? (string) $row['.id'] : $session->router_active_id,
                'router_authorized_at' => $session->router_authorized_at ?: now(),
                'router_last_seen_at' => now(),
                'last_seen_at' => now(),
            ])->save();

            if ($session->network_session_id) {
                NetworkSession::query()
                    ->whereKey($session->network_session_id)
                    ->update([
                        'upload_bytes' => max(0, (int) ($row['bytes-in'] ?? 0)),
                        'download_bytes' => max(0, (int) ($row['bytes-out'] ?? 0)),
                        'last_activity_at' => now(),
                        'router_username' => $username !== '' ? $username : null,
                        'router_active_id' => isset($row['.id']) ? (string) $row['.id'] : null,
                    ]);
            }
        }

        $grace = max(30, (int) config('mikrotik.missing_session_grace_seconds', 120));

        foreach ($sessions as $session) {
            if (isset($seen[$session->id]) || ! $session->router_last_seen_at) {
                continue;
            }

            if ($session->router_last_seen_at->gt(now()->subSeconds($grace))) {
                continue;
            }

            if ($session->network_session_id) {
                NetworkSession::query()
                    ->whereKey($session->network_session_id)
                    ->where('status', 'active')
                    ->update(['status' => 'ended', 'ended_at' => now()]);
            }

            if ($session->status === CaptiveSession::STATUS_AUTHENTICATED) {
                $session->forceFill(['status' => CaptiveSession::STATUS_DISCONNECTED])->save();
            }
        }

        $this->removeStaleUsers($router, $mikroTikService, $byUsername);
    }

    /**
     * Remove router users whose access grant is no longer usable.
     *
     * @param  Collection<string, CaptiveSession>  $byUsername
     */
    private function removeStaleUsers(NetworkDevice $router, MikroTikService $mikroTikService, $byUsername): void
    {
        foreach ($mikroTikService->users($router) as $row) {
            $name = (string) ($row['name'] ?? '');
            $comment = (string) ($row['comment'] ?? '');

            if (! str_starts_with($comment, 'wf|')) {
                continue;
            }

            $session = $byUsername->get($name);

            // Keep the router-side login (and its cookie) for as long as the
            // access grant is valid, even if the hotspot entry is temporarily
            // missing. Deleting it here makes a returning device unable to
            // resume and breaks single-use vouchers.
            if ($session && $session->accessGrant?->isUsable()) {
                continue;
            }

            $mikroTikService->removeByMac(
                $router,
                (string) ($session?->client_mac ?? ''),
                [$name],
            );

            $session?->forceFill([
                'mikrotik_username' => null,
                'mikrotik_password' => null,
                'mikrotik_user_id' => null,
                'mikrotik_grant_id' => null,
                'mikrotik_provisioned_at' => null,
                'router_active_id' => null,
            ])->save();
        }
    }

    private function normalizeMac(?string $mac): ?string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', (string) $mac) ?? '');

        if (strlen($clean) !== 12) {
            return null;
        }

        return implode(':', str_split($clean, 2));
    }
}
