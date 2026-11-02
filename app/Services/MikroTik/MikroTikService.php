<?php

namespace App\Services\MikroTik;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RouterOS REST API client and hotspot provisioning.
 *
 * The captive portal uses a one-time local hotspot user instead of WiFiDog:
 * after payment the backend creates the user on the router, and the browser
 * auto-submits those credentials to the router's /login endpoint.
 */
class MikroTikService
{
    public function test(NetworkDevice $router): array
    {
        $started = microtime(true);

        try {
            $this->assertConfigured($router);

            $resource = $this->get($router, '/system/resource');
            $active = $this->activeClients($router);

            return [
                'gateway_type' => $router->gateway_type,
                'reachable' => true,
                'authenticated' => true,
                'routeros_version' => isset($resource['version']) ? (string) $resource['version'] : null,
                'uptime' => isset($resource['uptime']) ? (string) $resource['uptime'] : null,
                'active_clients' => count($active),
                'hotspot_enabled' => true,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => 'RouterOS REST API is reachable and authenticated.',
            ];
        } catch (MikroTikException $exception) {
            return $this->failedTestResult($router, $exception, $started);
        } catch (\Throwable $exception) {
            Log::warning('MikroTik test failed', [
                'router_id' => $router->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->failedTestResult(
                $router,
                new MikroTikException('RouterOS REST request failed: '.$exception->getMessage()),
                $started,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeClients(NetworkDevice $router): array
    {
        return $this->getList($router, '/ip/hotspot/active');
    }

    /**
     * Ensure the router has a per-plan hotspot user profile and a one-time
     * hotspot user for this captive session. Safe to call repeatedly.
     */
    public function ensureProvisioned(CaptiveSession $session): void
    {
        $session->loadMissing(['networkDevice', 'accessGrant.internetPlan']);

        $router = $session->networkDevice;

        if (! $router || $router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            throw new MikroTikException('This captive session is not attached to a MikroTik router.');
        }

        if (! $session->isAuthenticated()) {
            throw new MikroTikException('The captive session is not authenticated yet.');
        }

        $grant = $session->accessGrant;

        if (! $grant || ! $grant->isUsable()) {
            throw new MikroTikException('The access grant for this session is no longer usable.');
        }

        if (
            $session->mikrotik_provisioned_at
            && (int) $session->mikrotik_grant_id === (int) $grant->id
            && filled($session->mikrotik_username)
            && filled($session->mikrotik_password)
        ) {
            $existing = $this->findByName($this->getList($router, '/ip/hotspot/user'), (string) $session->mikrotik_username);

            if ($existing) {
                return;
            }

            // The router lost the user (reboot/config reset). Fall through and
            // recreate it instead of trusting the local provisioned flag.
        }

        $plan = $grant->internetPlan;

        if (! $plan) {
            throw new MikroTikException('The access grant is not linked to a package.');
        }

        $profile = $this->ensureProfile($router, $plan);
        $username = $this->usernameFor($session);
        $password = $this->passwordFor($session);
        $payload = $this->userPayload($session, $profile, $username, $password);

        $existing = $this->findByName($this->getList($router, '/ip/hotspot/user'), $username);

        if ($existing) {
            $set = $payload;
            unset($set['name']);
            $set['.id'] = $existing['.id'] ?? null;
            $this->post($router, '/ip/hotspot/user/set', array_filter($set, fn ($value) => $value !== null));
            $userId = isset($existing['.id']) ? (string) $existing['.id'] : null;
        } else {
            $created = $this->post($router, '/ip/hotspot/user/add', $payload);
            $userId = isset($created['.id']) ? (string) $created['.id'] : null;
        }

        $session->forceFill([
            'mikrotik_username' => $username,
            'mikrotik_password' => Crypt::encryptString($password),
            'mikrotik_user_id' => $userId,
            'mikrotik_grant_id' => $grant->id,
            'mikrotik_provisioned_at' => now(),
        ])->save();

        Log::channel('wifidog')->info('MIKROTIK PROVISIONED', [
            'gateway_id' => $router->id,
            'captive_session_id' => $session->id,
            'username' => $username,
            'profile' => $profile,
            'access_grant_id' => $grant->id,
        ]);
    }

    /**
     * Remove the active session, hotspot user and cookie for a captive session.
     *
     * @return array{active:int, users:int, cookies:int, usernames:list<string>}
     */
    public function revoke(CaptiveSession $session): array
    {
        $session->loadMissing('networkDevice');

        $router = $session->networkDevice;

        if (! $router || $router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            return ['active' => 0, 'users' => 0, 'cookies' => 0, 'usernames' => []];
        }

        $usernames = filled($session->mikrotik_username) ? [(string) $session->mikrotik_username] : [];

        return $this->removeByMac($router, (string) $session->client_mac, $usernames);
    }

    /**
     * Remove every router-side trace of a client MAC.
     *
     * @param  list<string>  $usernames
     * @return array{active:int, users:int, cookies:int, usernames:list<string>}
     */
    public function removeByMac(NetworkDevice $router, string $mac, array $usernames = []): array
    {
        $this->assertConfigured($router);

        $normalizedMac = $this->normalizeMac($mac);
        $usernames = array_values(array_unique(array_filter(array_map('strval', $usernames))));
        $removedActive = 0;
        $removedUsers = 0;
        $removedCookies = 0;

        foreach ($this->getList($router, '/ip/hotspot/active') as $row) {
            $rowMac = $this->normalizeMac((string) ($row['mac-address'] ?? ''));
            $rowUser = (string) ($row['user'] ?? '');

            $matchesMac = $normalizedMac !== null && $rowMac === $normalizedMac;
            $matchesUser = $rowUser !== '' && in_array($rowUser, $usernames, true);

            if (! $matchesMac && ! $matchesUser) {
                continue;
            }

            if ($rowUser !== '') {
                $usernames[] = $rowUser;
            }

            $id = isset($row['.id']) ? (string) $row['.id'] : '';
            if ($id !== '' && $this->removeQuietly($router, '/ip/hotspot/active', $id)) {
                $removedActive++;
            }
        }

        $usernames = array_values(array_unique(array_filter($usernames)));

        if ($usernames !== []) {
            foreach ($this->getList($router, '/ip/hotspot/user') as $row) {
                $name = (string) ($row['name'] ?? '');

                if (! in_array($name, $usernames, true)) {
                    continue;
                }

                $id = isset($row['.id']) ? (string) $row['.id'] : '';
                if ($id !== '' && $this->removeQuietly($router, '/ip/hotspot/user', $id)) {
                    $removedUsers++;
                }
            }
        }

        if ($normalizedMac !== null) {
            foreach ($this->getList($router, '/ip/hotspot/cookie') as $row) {
                if ($this->normalizeMac((string) ($row['mac-address'] ?? '')) !== $normalizedMac) {
                    continue;
                }

                $id = isset($row['.id']) ? (string) $row['.id'] : '';
                if ($id !== '' && $this->removeQuietly($router, '/ip/hotspot/cookie', $id)) {
                    $removedCookies++;
                }
            }
        }

        return [
            'active' => $removedActive,
            'users' => $removedUsers,
            'cookies' => $removedCookies,
            'usernames' => $usernames,
        ];
    }

    /**
     * @return array{resource: array<string, mixed>, active: list<array<string, mixed>>}
     */
    public function syncRouter(NetworkDevice $router): array
    {
        return [
            'resource' => $this->get($router, '/system/resource'),
            'active' => $this->activeClients($router),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function users(NetworkDevice $router): array
    {
        return $this->getList($router, '/ip/hotspot/user');
    }

    public function ensureProfile(NetworkDevice $router, InternetPlan $plan): string
    {
        $name = $this->profileName($plan);
        $payload = [
            'name' => $name,
            'shared-users' => (string) max(1, (int) ($plan->devices_allowed ?: 1)),
        ];

        $rateLimit = $this->rateLimit($plan);
        if ($rateLimit !== null) {
            $payload['rate-limit'] = $rateLimit;
        }

        $existing = $this->findByName($this->getList($router, '/ip/hotspot/user/profile'), $name);

        if ($existing) {
            $set = $payload;
            unset($set['name']);
            $set['.id'] = $existing['.id'] ?? null;
            $this->post($router, '/ip/hotspot/user/profile/set', array_filter($set, fn ($value) => $value !== null));
        } else {
            $this->post($router, '/ip/hotspot/user/profile/add', $payload);
        }

        return $name;
    }

    /**
     * Exposed for tests and diagnostics.
     *
     * @return array<string, string>
     */
    public function userPayload(CaptiveSession $session, string $profile, string $username, string $password): array
    {
        $session->loadMissing('accessGrant.internetPlan');

        $grant = $session->accessGrant;
        $plan = $grant?->internetPlan;

        if (! $grant) {
            throw new MikroTikException('The captive session has no access grant.');
        }

        $payload = [
            'name' => $username,
            'password' => $password,
            'profile' => $profile,
            'comment' => sprintf(
                'wf|%d|%d|%s',
                $session->company_id,
                $session->id,
                $grant->expires_at?->toIso8601String() ?? 'unlimited',
            ),
            'disabled' => 'no',
        ];

        $uptime = $this->uptimeLimit($grant, $plan);
        if ($uptime !== null) {
            $payload['limit-uptime'] = $uptime;
        }

        $bytes = $this->bytesLimit($grant, $plan);
        if ($bytes !== null) {
            $payload['limit-bytes-total'] = (string) $bytes;
        }

        return $payload;
    }

    public function normalizeMac(?string $mac): ?string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', (string) $mac) ?? '');

        if (strlen($clean) !== 12) {
            return null;
        }

        return implode(':', str_split($clean, 2));
    }

    private function usernameFor(CaptiveSession $session): string
    {
        return 'wf_'.$session->token;
    }

    private function passwordFor(CaptiveSession $session): string
    {
        if (filled($session->mikrotik_password)) {
            try {
                return Crypt::decryptString((string) $session->mikrotik_password);
            } catch (\Throwable) {
                // Regenerate below.
            }
        }

        return Str::random(24);
    }

    private function profileName(InternetPlan $plan): string
    {
        $name = filled($plan->mikrotik_profile)
            ? (string) $plan->mikrotik_profile
            : 'wf-'.$plan->company_id.'-'.$plan->id;

        $name = preg_replace('/[^A-Za-z0-9_\-]/', '-', $name) ?: 'wf-'.$plan->id;

        return mb_substr($name, 0, 64);
    }

    private function rateLimit(InternetPlan $plan): ?string
    {
        $download = $plan->speed_download_mbps;
        $upload = $plan->speed_upload_mbps;

        if (! $download && ! $upload) {
            return null;
        }

        return ($download ?: $upload).'M/'.($upload ?: $download).'M';
    }

    private function uptimeLimit(AccessGrant $grant, ?InternetPlan $plan): ?string
    {
        if ($grant->expires_at) {
            $seconds = $grant->expires_at->getTimestamp() - now()->getTimestamp();

            if ($seconds <= 0) {
                throw new MikroTikException('The access grant has expired.');
            }

            return $this->formatDuration($seconds);
        }

        if (! $plan || blank($plan->duration) || $plan->duration_unit === 'UNLIMITED_DATA') {
            return null;
        }

        $seconds = match ($plan->duration_unit) {
            'HOURS' => (int) $plan->duration * 3600,
            'DAYS' => (int) $plan->duration * 86400,
            'WEEKS' => (int) $plan->duration * 604800,
            'MONTHS' => (int) $plan->duration * 2592000,
            default => (int) $plan->duration * 3600,
        };

        return $seconds > 0 ? $this->formatDuration($seconds) : null;
    }

    private function bytesLimit(AccessGrant $grant, ?InternetPlan $plan): ?int
    {
        // data_limit is stored in bytes when used; data_cap_mb is in MB.
        if ($grant->data_limit !== null) {
            return max(0, (int) $grant->data_limit - (int) $grant->data_used);
        }

        if ($plan?->data_cap_mb) {
            return (int) $plan->data_cap_mb * 1024 * 1024;
        }

        return null;
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(1, $seconds);
        $output = '';

        foreach (['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1] as $suffix => $size) {
            if ($seconds >= $size) {
                $output .= intdiv($seconds, $size).$suffix;
                $seconds %= $size;
            }
        }

        return $output !== '' ? $output : '1m';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function findByName(array $rows, string $name): ?array
    {
        foreach ($rows as $row) {
            if ((string) ($row['name'] ?? '') === $name) {
                return $row;
            }
        }

        return null;
    }

    private function removeQuietly(NetworkDevice $router, string $path, string $id): bool
    {
        try {
            $this->delete($router, $path.'/'.$id);

            return true;
        } catch (MikroTikException $exception) {
            // Already removed by the router / another request. Treat as done.
            if (in_array($exception->httpStatus(), [400, 404], true)) {
                return true;
            }

            Log::warning('MikroTik remove failed', [
                'router_id' => $router->id,
                'path' => $path,
                'status' => $exception->httpStatus(),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function assertConfigured(NetworkDevice $router): void
    {
        if ($router->gateway_type !== NetworkDevice::GATEWAY_MIKROTIK) {
            throw new MikroTikException('Only MikroTik routers support this operation.');
        }

        if (blank($this->host($router))) {
            throw new MikroTikException('This router has no API host or LAN IP.');
        }

        if (blank($router->api_username) || blank($router->api_password)) {
            throw new MikroTikException('MikroTik API username and password are required.');
        }
    }

    private function host(NetworkDevice $router): string
    {
        $host = trim((string) ($router->api_host ?: $router->lan_ip));

        return (string) preg_replace('#^https?://#i', '', rtrim($host, '/'));
    }

    private function password(NetworkDevice $router): string
    {
        try {
            return Crypt::decryptString((string) $router->api_password);
        } catch (\Throwable) {
            throw new MikroTikException('The stored MikroTik API password cannot be read. Update the router and try again.');
        }
    }

    private function client(NetworkDevice $router): PendingRequest
    {
        $request = Http::withBasicAuth((string) $router->api_username, $this->password($router))
            ->acceptJson()
            ->timeout(max(1, (int) config('mikrotik.timeout', 10)));

        $verify = $router->api_verify_tls ?? (bool) config('mikrotik.verify_tls', true);

        return $verify ? $request : $request->withoutVerifying();
    }

    private function url(NetworkDevice $router, string $path): string
    {
        $port = (int) ($router->api_port ?: 443);
        $scheme = $port === 80 ? 'http' : 'https';

        return sprintf('%s://%s:%d/rest%s', $scheme, $this->host($router), $port, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(NetworkDevice $router, string $path): array
    {
        try {
            $response = $this->client($router)->get($this->url($router, $path));
        } catch (ConnectionException $exception) {
            throw new MikroTikException('Could not reach the router API.', null, [
                'router_id' => $router->id,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            throw new MikroTikException('Router API request failed: '.$exception->getMessage(), null, [
                'router_id' => $router->id,
                'path' => $path,
            ]);
        }

        if ($response->failed()) {
            throw $this->failure($router, $path, $response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getList(NetworkDevice $router, string $path): array
    {
        return $this->asList($this->get($router, $path));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(NetworkDevice $router, string $path, array $payload): array
    {
        try {
            $response = $this->client($router)->post($this->url($router, $path), $payload);
        } catch (ConnectionException $exception) {
            throw new MikroTikException('Could not reach the router API.', null, [
                'router_id' => $router->id,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            throw new MikroTikException('Router API request failed: '.$exception->getMessage(), null, [
                'router_id' => $router->id,
                'path' => $path,
            ]);
        }

        if ($response->failed()) {
            throw $this->failure($router, $path, $response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function delete(NetworkDevice $router, string $path): void
    {
        try {
            $response = $this->client($router)->delete($this->url($router, $path));
        } catch (ConnectionException $exception) {
            throw new MikroTikException('Could not reach the router API.', null, [
                'router_id' => $router->id,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            throw new MikroTikException('Router API request failed: '.$exception->getMessage(), null, [
                'router_id' => $router->id,
                'path' => $path,
            ]);
        }

        if ($response->failed()) {
            throw $this->failure($router, $path, $response);
        }
    }

    private function failure(NetworkDevice $router, string $path, Response $response): MikroTikException
    {
        $status = $response->status();
        $providerMessage = $response->json('message')
            ?? $response->json('error')
            ?? $response->json('detail');

        $message = match (true) {
            in_array($status, [401, 403], true) => 'Router API authentication failed. Check the API username, password, and policy.',
            $status === 404 => 'Router API endpoint not found. Enable the RouterOS www-ssl service and REST API.',
            default => 'Router API rejected the request (HTTP '.$status.').',
        };

        if (is_string($providerMessage) && $providerMessage !== '') {
            $message .= ' '.mb_substr($providerMessage, 0, 200);
        }

        Log::warning('MikroTik API request failed', [
            'router_id' => $router->id,
            'path' => $path,
            'status' => $status,
            'message' => $message,
        ]);

        return new MikroTikException($message, $status, [
            'router_id' => $router->id,
            'path' => $path,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function asList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return isset($value['.id']) || isset($value['name']) ? [$value] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function failedTestResult(NetworkDevice $router, MikroTikException $exception, float $started): array
    {
        return [
            'gateway_type' => $router->gateway_type,
            'reachable' => $exception->httpStatus() !== null,
            'authenticated' => false,
            'routeros_version' => null,
            'uptime' => null,
            'active_clients' => 0,
            'hotspot_enabled' => null,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'message' => $exception->getMessage(),
        ];
    }
}
