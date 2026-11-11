<?php

namespace App\Services\Omada;

use App\Models\AccessGrant;
use App\Models\OmadaClientSession;
use App\Models\OmadaIntegration;
use App\Models\OmadaSite;
use App\Models\OmadaSsid;
use App\Models\PaymentTransaction;
use Illuminate\Validation\ValidationException;

class OmadaPortalService
{
    public function __construct(
        private readonly OmadaSessionService $sessions,
    ) {}

    /**
     * Validate the parameters Omada appends to the external portal URL and
     * resolve them to a tenant integration. Client-supplied values are never
     * treated as proof of payment.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validateParameters(OmadaIntegration $integration, array $input): array
    {
        $clientMac = $this->normalizeMac((string) ($input['clientMac'] ?? $input['client_mac'] ?? ''));

        if ($clientMac === '') {
            throw ValidationException::withMessages(['clientMac' => ['A valid Omada client MAC address is required.']]);
        }

        $site = isset($input['site']) ? trim((string) $input['site']) : null;

        if (filled($site)) {
            if (filled($integration->site_name) && ! hash_equals((string) $integration->site_name, (string) $site)) {
                throw ValidationException::withMessages(['site' => ['This portal request belongs to a different Omada site.']]);
            }

            $knownSites = OmadaSite::query()
                ->where('company_id', $integration->company_id)
                ->where('omada_integration_id', $integration->id)
                ->pluck('site_name')
                ->all();

            if ($knownSites !== [] && ! in_array($site, $knownSites, true)) {
                throw ValidationException::withMessages(['site' => ['This Omada site is not linked to WaiFai.']]);
            }
        }

        $ssid = isset($input['ssidName']) ? trim((string) $input['ssidName']) : null;

        if (filled($ssid)) {
            $selected = OmadaSsid::query()
                ->where('company_id', $integration->company_id)
                ->where('omada_integration_id', $integration->id)
                ->where('selected_for_waifai', true)
                ->pluck('ssid_name')
                ->all();

            if ($selected !== [] && ! in_array($ssid, $selected, true)) {
                throw ValidationException::withMessages(['ssidName' => ['This SSID is not enabled for WaiFai authentication.']]);
            }
        }

        $timestamp = $input['t'] ?? null;
        if (filled($timestamp) && (! is_numeric($timestamp) || abs(time() - (int) $timestamp) > 86400)) {
            throw ValidationException::withMessages(['t' => ['The Omada portal timestamp is stale or invalid.']]);
        }

        return [
            'client_mac' => $clientMac,
            'client_ip' => $this->validateIp($input['clientIp'] ?? null),
            'ap_mac' => $this->normalizeMac((string) ($input['apMac'] ?? '')),
            'gateway_mac' => $this->normalizeMac((string) ($input['gatewayMac'] ?? '')),
            'ssid_name' => $ssid,
            'site' => $site,
            'radio_id' => $input['radioId'] ?? null,
            'vid' => $input['vid'] ?? null,
            'redirect_url' => $this->safeRedirectUrl($input['redirectUrl'] ?? $input['url'] ?? null),
            't' => $timestamp,
        ];
    }

    public function findIntegration(string $portalKey): OmadaIntegration
    {
        $integration = OmadaIntegration::query()
            ->with('company')
            ->where('portal_key', $portalKey)
            ->where('status', 'active')
            ->first();

        if (! $integration || ! $integration->company || $integration->company->status !== 'active') {
            throw ValidationException::withMessages(['portal' => ['This Omada portal link is not active.']]);
        }

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function entry(OmadaIntegration $integration, array $params): array
    {
        $session = $this->sessions->open($integration, $params);
        $company = $integration->company;

        return [
            'integration' => [
                'id' => $integration->id,
                'name' => $integration->name,
                'deployment_type' => $integration->deployment_type,
                'site_id' => $integration->site_id,
                'site_name' => $integration->site_name,
            ],
            'client' => [
                'mac' => $session->client_mac,
                'ip' => $session->client_ip,
                'ap_mac' => $session->ap_mac,
                'ssid' => $session->ssid_name,
            ],
            'client_session_id' => $session->id,
            'authorization_status' => $session->authorization_status,
            'portal_url' => $company?->subdomain ? url('/connect?subdomain='.$company->subdomain) : url('/connect'),
            'authorize_url' => url('/api/omada/portal/'.$integration->portal_key.'/authorize'),
        ];
    }

    public function authorizeByPayment(OmadaIntegration $integration, int $sessionId, int $paymentTransactionId): OmadaClientSession
    {
        $session = OmadaClientSession::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->findOrFail($sessionId);

        $payment = PaymentTransaction::query()
            ->where('company_id', $integration->company_id)
            ->findOrFail($paymentTransactionId);

        return $this->sessions->authorize($session, $payment);
    }

    public function authorizeByGrant(OmadaIntegration $integration, int $sessionId, int $accessGrantId): OmadaClientSession
    {
        $session = OmadaClientSession::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->findOrFail($sessionId);

        $grant = AccessGrant::query()
            ->where('company_id', $integration->company_id)
            ->findOrFail($accessGrantId);

        return $this->sessions->authorize($session, null, $grant);
    }

    private function normalizeMac(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');

        return strlen($hex) === 12 ? implode('-', str_split($hex, 2)) : '';
    }

    private function validateIp(mixed $ip): ?string
    {
        if (! filled($ip)) {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? (string) $ip : null;
    }

    private function safeRedirectUrl(mixed $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $url = (string) $url;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
