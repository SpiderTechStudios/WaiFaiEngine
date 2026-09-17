<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptiveSessionService
{
    public function __construct(
        private NetworkSessionService $networkSessionService,
    ) {}

    /**
     * Create or reuse a captive session for a gateway client.
     *
     * @param  array{ip?: ?string, mac?: ?string, ssid?: ?string, gw_address?: ?string, gw_port?: ?int|string, url?: ?string}  $client
     */
    public function resolveOrCreate(NetworkDevice $gateway, array $client): CaptiveSession
    {
        $mac = isset($client['mac']) && filled($client['mac'])
            ? $this->normalizeMacOptional((string) $client['mac'])
            : null;
        $ip = isset($client['ip']) && filled($client['ip']) ? (string) $client['ip'] : null;

        return DB::transaction(function () use ($gateway, $client, $mac, $ip) {
            $existing = $this->findReusableSession($gateway, $mac, $ip);

            $gwAddress = filled($client['gw_address'] ?? null)
                ? (string) $client['gw_address']
                : ($gateway->lan_ip ?: null);
            $gwPort = isset($client['gw_port']) && filled($client['gw_port'])
                ? (int) $client['gw_port']
                : ($gateway->wifidog_port ?: 2060);

            if ($existing) {
                $existing->forceFill([
                    'client_ip' => $ip ?? $existing->client_ip,
                    'client_mac' => $mac ?? $existing->client_mac,
                    'ssid' => $client['ssid'] ?? $existing->ssid,
                    'gw_address' => $gwAddress ?? $existing->gw_address,
                    'gw_port' => $gwPort ?: $existing->gw_port,
                    'requested_url' => $client['url'] ?? $existing->requested_url,
                    'last_seen_at' => now(),
                    'expires_at' => $existing->isAuthenticated()
                        ? $existing->expires_at
                        : now()->addMinutes($this->ttlMinutes()),
                ])->save();

                Log::info('wifidog.captive_session_reused', [
                    'gw_id' => $gateway->gateway_id,
                    'gateway_id' => $gateway->id,
                    'network_id' => $gateway->network_station_id,
                    'client_mac' => $existing->client_mac,
                    'client_ip' => $existing->client_ip,
                    'gw_address' => $existing->gw_address,
                    'session_token_hash' => hash('sha256', $existing->token),
                    'status' => $existing->status,
                ]);

                return $existing->fresh();
            }

            $session = CaptiveSession::query()->create([
                'company_id' => $gateway->company_id,
                'network_device_id' => $gateway->id,
                'network_station_id' => $gateway->network_station_id,
                'gateway_id' => (string) $gateway->gateway_id,
                'client_mac' => $mac,
                'client_ip' => $ip,
                'ssid' => $client['ssid'] ?? null,
                'gw_address' => $gwAddress,
                'gw_port' => $gwPort,
                'requested_url' => $client['url'] ?? null,
                'token' => $this->generateToken(),
                'status' => CaptiveSession::STATUS_PENDING,
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
                'last_seen_at' => now(),
            ]);

            Log::info('wifidog.captive_session_created', [
                'gw_id' => $gateway->gateway_id,
                'gateway_id' => $gateway->id,
                'network_id' => $gateway->network_station_id,
                'client_mac' => $session->client_mac,
                'client_ip' => $session->client_ip,
                'gw_address' => $session->gw_address,
                'session_token_hash' => hash('sha256', $session->token),
                'status' => $session->status,
            ]);

            return $session;
        });
    }

    public function findByToken(string $token): ?CaptiveSession
    {
        $session = CaptiveSession::query()
            ->with(['company', 'networkDevice', 'networkStation', 'accessGrant'])
            ->where('token', $token)
            ->first();

        if ($session) {
            $session->markExpiredIfNeeded();
        }

        return $session;
    }

    /**
     * Mark a captive session authenticated after voucher/payment success.
     *
     * @param  array{access_grant_id?: int, payment_transaction_id?: int, mac_address?: ?string, ip_address?: ?string}  $data
     */
    public function authenticate(CaptiveSession $session, array $data): CaptiveSession
    {
        if ($session->markExpiredIfNeeded()) {
            throw ValidationException::withMessages([
                'session' => ['Captive session has expired.'],
            ]);
        }

        if ($session->status === CaptiveSession::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'session' => ['Captive session was rejected.'],
            ]);
        }

        return DB::transaction(function () use ($session, $data) {
            $session = CaptiveSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $session->loadMissing('company');

            $mac = isset($data['mac_address']) && filled($data['mac_address'])
                ? $this->normalizeMacOptional((string) $data['mac_address'])
                : $session->client_mac;

            $hotspotSession = $this->networkSessionService->create($session->company, [
                'access_grant_id' => $data['access_grant_id'] ?? null,
                'payment_transaction_id' => $data['payment_transaction_id'] ?? null,
                'mac_address' => $mac,
                'ip_address' => $data['ip_address'] ?? $session->client_ip,
                'router_id' => $session->network_device_id,
                'network_station_id' => $session->network_station_id,
                'session_id' => $session->token,
            ]);

            return $this->attachHotspotSession($session, $hotspotSession, $mac, $data['ip_address'] ?? null);
        });
    }

    /**
     * Link an already-created hotspot NetworkSession to the captive session.
     */
    public function linkHotspotSession(CaptiveSession $session, NetworkSession $hotspotSession): CaptiveSession
    {
        if ($session->markExpiredIfNeeded()) {
            throw ValidationException::withMessages([
                'session' => ['Captive session has expired.'],
            ]);
        }

        if ((int) $session->company_id !== (int) $hotspotSession->company_id) {
            throw ValidationException::withMessages([
                'session' => ['Captive session does not belong to this network.'],
            ]);
        }

        return DB::transaction(function () use ($session, $hotspotSession) {
            $session = CaptiveSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            return $this->attachHotspotSession(
                $session,
                $hotspotSession,
                $hotspotSession->mac_address,
                $hotspotSession->ip_address,
            );
        });
    }

    private function attachHotspotSession(
        CaptiveSession $session,
        NetworkSession $hotspotSession,
        ?string $mac,
        ?string $ip,
    ): CaptiveSession {
        $session->forceFill([
            'status' => CaptiveSession::STATUS_AUTHENTICATED,
            'authenticated_at' => now(),
            'access_grant_id' => $hotspotSession->access_grant_id,
            'network_session_id' => $hotspotSession->id,
            'client_mac' => $mac ?? $session->client_mac,
            'client_ip' => $ip ?? $session->client_ip,
            'last_seen_at' => now(),
            'expires_at' => $this->extendAuthenticatedExpiry($hotspotSession),
        ])->save();

        Log::info('wifidog.captive_session_authenticated', [
            'gw_id' => $session->gateway_id,
            'gateway_id' => $session->network_device_id,
            'network_id' => $session->network_station_id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $session->token),
            'access_grant_id' => $hotspotSession->access_grant_id,
            'decision' => 'authenticated',
        ]);

        return $session->fresh()->load(['company', 'networkDevice', 'accessGrant', 'networkSession']);
    }

    public function authorizeToken(string $token, ?string $mac = null, ?string $ip = null): bool
    {
        $session = $this->findByToken($token);

        if (! $session) {
            Log::info('wifidog.auth_denied', [
                'reason' => 'unknown_token',
                'session_token_hash' => hash('sha256', $token),
            ]);

            return false;
        }

        if ($session->markExpiredIfNeeded() || ! $session->isAuthenticated()) {
            Log::info('wifidog.auth_denied', [
                'reason' => 'not_authenticated_or_expired',
                'gw_id' => $session->gateway_id,
                'gateway_id' => $session->network_device_id,
                'client_mac' => $session->client_mac,
                'status' => $session->status,
                'session_token_hash' => hash('sha256', $token),
            ]);

            return false;
        }

        if ($mac) {
            $normalized = $this->normalizeMacOptional($mac);
            if ($session->client_mac && $normalized && strcasecmp($session->client_mac, $normalized) !== 0) {
                Log::info('wifidog.auth_denied', [
                    'reason' => 'mac_mismatch',
                    'gw_id' => $session->gateway_id,
                    'gateway_id' => $session->network_device_id,
                    'client_mac' => $session->client_mac,
                    'request_mac' => $normalized,
                    'session_token_hash' => hash('sha256', $token),
                ]);

                return false;
            }
        }

        $session->forceFill([
            'last_seen_at' => now(),
            'client_ip' => $ip ?: $session->client_ip,
        ])->save();

        if ($session->access_grant_id) {
            $grant = AccessGrant::query()
                ->where('company_id', $session->company_id)
                ->whereKey($session->access_grant_id)
                ->first();

            if (! $grant || $grant->status !== 'active' || ($grant->expires_at && $grant->expires_at->isPast())) {
                Log::info('wifidog.auth_denied', [
                    'reason' => 'access_grant_invalid',
                    'gw_id' => $session->gateway_id,
                    'gateway_id' => $session->network_device_id,
                    'session_token_hash' => hash('sha256', $token),
                ]);

                return false;
            }
        }

        Log::info('wifidog.auth_allowed', [
            'gw_id' => $session->gateway_id,
            'gateway_id' => $session->network_device_id,
            'network_id' => $session->network_station_id,
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'session_token_hash' => hash('sha256', $token),
            'decision' => 'authorized',
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $extra  Additional query params (e.g. mac, ip, gw_address, gw_port).
     */
    public function portalRedirectUrl(CaptiveSession $session, array $extra = []): string
    {
        $session->loadMissing('company');

        $portalPage = $this->resolvePortalPageUrl();

        $query = [
            'subdomain' => $session->company->subdomain,
            'session' => $session->token,
        ];

        foreach ($extra as $key => $value) {
            if (filled($value)) {
                $query[$key] = $value;
            }
        }

        $url = $portalPage.'?'.http_build_query($query);

        $this->assertSafePortalRedirectUrl($url);

        return $url;
    }

    /**
     * Build the absolute connect-page URL (no query string).
     */
    public function resolvePortalPageUrl(): string
    {
        $connectPath = '/'.trim((string) config('captive.portal_connect_path', '/connect'), '/');
        if ($connectPath === '/') {
            $connectPath = '/connect';
        }

        $configured = trim((string) (config('captive.portal_url') ?: config('captive.portal_base_url') ?: ''));
        $configured = rtrim($configured, '/');

        if ($configured === '') {
            $configured = rtrim((string) config('captive.portal_origin', config('app.url')), '/');
        }

        if ($configured === '' || ! preg_match('#^https?://#i', $configured)) {
            throw new \RuntimeException(
                'CAPTIVE_PORTAL_URL must be an absolute http(s) URL to the customer captive portal (e.g. https://waifai.cloud.shereheyangu.com/connect).'
            );
        }

        $path = parse_url($configured, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        // Full connect URL already configured.
        if ($path === $connectPath || str_ends_with($path, $connectPath)) {
            return $configured;
        }

        // Origin-only (or other path) — append connect path.
        return $configured.$connectPath;
    }

    private function assertSafePortalRedirectUrl(string $url): void
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        if (! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \RuntimeException('Captive portal redirect must be an absolute http(s) URL.');
        }

        if (str_contains($path, '/api/wifidog') || str_contains($path, '/api/v1/wifidog')) {
            throw new \RuntimeException(
                'Captive portal URL must not point at the WiFiDog API. Set CAPTIVE_PORTAL_URL to the frontend connect page (e.g. https://waifai.cloud.shereheyangu.com/connect), not /api/wifidog/login. On the Ruijie/WiFiDog gateway, AuthServer Path must be /api/wifidog/ (not /api/wifidog/login/).'
            );
        }

        // Relative Location headers resolve against / and produce /api/wifidog/login/login.
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            throw new \RuntimeException('Captive portal redirect must be absolute to avoid /api/wifidog/login/login.');
        }
    }

    public function gatewayAuthRedirectUrl(CaptiveSession $session): ?string
    {
        $session->loadMissing('networkDevice');

        $address = $session->gw_address ?: $session->networkDevice?->lan_ip;
        $port = $session->gw_port
            ?: $session->networkDevice?->wifidog_port
            ?: 2060;

        if (blank($address)) {
            Log::warning('wifidog.gateway_auth_url_missing_address', [
                'captive_session_id' => $session->id,
                'gateway_id' => $session->network_device_id,
                'status' => $session->status,
            ]);

            return null;
        }

        // Persist fallback so later polls / auth redirects stay consistent.
        if (blank($session->gw_address) || blank($session->gw_port)) {
            $session->forceFill([
                'gw_address' => $session->gw_address ?: $address,
                'gw_port' => $session->gw_port ?: $port,
            ])->save();
        }

        $query = http_build_query([
            'token' => $session->token,
        ]);

        if (filled($session->requested_url)) {
            $query .= '&url='.rawurlencode($session->requested_url);
        }

        return "http://{$address}:{$port}/wifidog/auth?{$query}";
    }

    /**
     * Safe payload for the frontend captive portal.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(CaptiveSession $session): array
    {
        $session->loadMissing(['company', 'networkDevice', 'networkStation']);

        return [
            'status' => $session->status,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'authenticated_at' => $session->authenticated_at?->toIso8601String(),
            'network' => [
                'name' => $session->networkStation?->name ?? $session->company->name,
                'subdomain' => $session->company->subdomain,
            ],
            'gateway' => [
                'name' => $session->networkDevice?->name,
            ],
            'client' => [
                'ip' => $session->client_ip,
                'mac' => $session->client_mac,
                'ssid' => $session->ssid,
            ],
            'gateway_auth_url' => $this->gatewayAuthRedirectUrl($session),
        ];
    }

    private function findReusableSession(NetworkDevice $gateway, ?string $mac, ?string $ip): ?CaptiveSession
    {
        $query = CaptiveSession::query()
            ->where('company_id', $gateway->company_id)
            ->where('network_device_id', $gateway->id)
            ->whereIn('status', [
                CaptiveSession::STATUS_PENDING,
                CaptiveSession::STATUS_AUTHENTICATED,
            ])
            ->where('expires_at', '>', now())
            ->orderByDesc('id');

        if ($mac) {
            $session = (clone $query)->where('client_mac', $mac)->first();
            if ($session) {
                return $session;
            }
        }

        if ($ip && ! $mac) {
            return (clone $query)
                ->whereNull('client_mac')
                ->where('client_ip', $ip)
                ->first();
        }

        return null;
    }

    private function generateToken(): string
    {
        return Str::lower(bin2hex(random_bytes(32)));
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('captive.session_ttl_minutes', 10));
    }

    private function extendAuthenticatedExpiry(NetworkSession $hotspotSession): \Carbon\CarbonInterface
    {
        $hotspotSession->loadMissing('accessGrant');
        $grantExpiry = $hotspotSession->accessGrant?->expires_at;

        if ($grantExpiry && $grantExpiry->isFuture()) {
            return $grantExpiry;
        }

        return now()->addHours(24);
    }

    private function normalizeMacOptional(string $mac): ?string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac) ?? '');

        if ($clean === '') {
            return null;
        }

        if (strlen($clean) !== 12) {
            throw ValidationException::withMessages([
                'mac' => ['MAC address must be 12 hex digits (e.g. AA:BB:CC:DD:EE:FF).'],
            ]);
        }

        return implode(':', str_split($clean, 2));
    }
}
