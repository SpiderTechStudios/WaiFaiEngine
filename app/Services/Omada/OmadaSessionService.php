<?php

namespace App\Services\Omada;

use App\Models\AccessGrant;
use App\Models\InternetPlan;
use App\Models\OmadaClientSession;
use App\Models\OmadaIntegration;
use App\Models\PaymentTransaction;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class OmadaSessionService
{
    public function __construct(
        private readonly OmadaAuthenticationService $authentication,
    ) {}

    /**
     * Create or reuse a pending session for an Omada client.
     *
     * @param  array<string, mixed>  $params
     */
    public function open(OmadaIntegration $integration, array $params): OmadaClientSession
    {
        $clientMac = $this->normalizeMac((string) ($params['client_mac'] ?? $params['clientMac'] ?? ''));

        if ($clientMac === '') {
            throw ValidationException::withMessages(['client_mac' => ['A valid client MAC address is required.']]);
        }

        $existing = OmadaClientSession::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->where('client_mac', $clientMac)
            ->whereIn('authorization_status', [OmadaClientSession::STATUS_PENDING, OmadaClientSession::STATUS_AUTHORIZED])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        $attributes = [
            'company_id' => $integration->company_id,
            'omada_integration_id' => $integration->id,
            'network_device_id' => $integration->network_device_id,
            'site_id' => $integration->site_id,
            'client_mac' => $clientMac,
            'client_ip' => $params['client_ip'] ?? $params['clientIp'] ?? null,
            'ap_mac' => $this->normalizeMac((string) ($params['ap_mac'] ?? $params['apMac'] ?? '')),
            'ssid_name' => $params['ssid_name'] ?? $params['ssidName'] ?? null,
            'metadata' => array_filter([
                'radio_id' => $params['radio_id'] ?? $params['radioId'] ?? null,
                'gateway_mac' => $params['gateway_mac'] ?? $params['gatewayMac'] ?? null,
                'vid' => $params['vid'] ?? null,
                'redirect_url' => $params['redirect_url'] ?? $params['redirectUrl'] ?? null,
                'timestamp' => $params['t'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing->fresh();
        }

        return OmadaClientSession::query()->create($attributes + [
            'authorization_status' => OmadaClientSession::STATUS_PENDING,
        ]);
    }

    /**
     * Authorize a client after the caller has verified an entitlement.
     *
     * @param  array<string, mixed>  $params
     */
    public function authorize(OmadaClientSession $session, ?PaymentTransaction $payment = null, ?AccessGrant $grant = null, array $params = []): OmadaClientSession
    {
        if ($session->authorization_status === OmadaClientSession::STATUS_AUTHORIZED
            && $session->expires_at
            && $session->expires_at->isFuture()) {
            return $session;
        }

        [$grant, $payment, $expiresAt, $duration] = $this->resolveEntitlement($session, $payment, $grant);

        $integration = OmadaIntegration::query()
            ->where('company_id', $session->company_id)
            ->findOrFail($session->omada_integration_id);

        $authParams = array_merge([
            'client_mac' => $session->client_mac,
            'client_ip' => $session->client_ip,
            'ap_mac' => $session->ap_mac,
            'ssid_name' => $session->ssid_name,
            'site' => $integration->site_name,
        ], $params);

        try {
            $result = $this->authentication->authorizeClient($integration, $authParams, $duration);
        } catch (OmadaException $exception) {
            $session->forceFill([
                'authorization_status' => OmadaClientSession::STATUS_FAILED,
                'last_sync_status' => 'authorization_failed',
                'metadata' => array_merge($session->metadata ?? [], ['last_error' => $exception->userMessage()]),
            ])->save();

            throw ValidationException::withMessages([
                'omada' => [$exception->userMessage()],
            ]);
        }

        $session->forceFill([
            'authorization_status' => OmadaClientSession::STATUS_AUTHORIZED,
            'authorized_at' => now(),
            'expires_at' => $expiresAt,
            'disconnected_at' => null,
            'access_grant_id' => $grant?->id,
            'payment_transaction_id' => $payment?->id ?? $session->payment_transaction_id,
            'package_id' => $grant?->internet_plan_id ?? $session->package_id,
            'controller_reference' => $result['client_mac'] ?? null,
            'last_sync_status' => 'authorized',
        ])->save();

        return $session->fresh();
    }

    public function disconnect(OmadaClientSession $session): OmadaClientSession
    {
        $integration = OmadaIntegration::query()
            ->where('company_id', $session->company_id)
            ->findOrFail($session->omada_integration_id);

        try {
            $this->authentication->disconnectClient($integration, $session->client_mac);
        } catch (OmadaException) {
            // Keep the local record accurate even if the controller is offline.
        }

        $session->forceFill([
            'authorization_status' => OmadaClientSession::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
            'last_sync_status' => 'disconnected',
        ])->save();

        return $session->fresh();
    }

    /**
     * Mark authorized sessions whose package expired and ask the controller to
     * disconnect them.
     *
     * @return int Number of expired sessions.
     */
    public function expireDue(): int
    {
        $due = OmadaClientSession::query()
            ->where('authorization_status', OmadaClientSession::STATUS_AUTHORIZED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit(200)
            ->get();

        foreach ($due as $session) {
            $this->disconnect($session);
            $session->forceFill([
                'authorization_status' => OmadaClientSession::STATUS_EXPIRED,
                'last_sync_status' => 'expired',
            ])->save();
        }

        return $due->count();
    }

    /**
     * Resolve the entitlement (grant or paid transaction) and duration for a
     * session without trusting client-supplied values.
     *
     * @return array{0: ?AccessGrant, 1: ?PaymentTransaction, 2: CarbonInterface, 3: int}
     */
    private function resolveEntitlement(OmadaClientSession $session, ?PaymentTransaction $payment, ?AccessGrant $grant): array
    {
        if ($grant) {
            if ((int) $grant->company_id !== (int) $session->company_id) {
                throw ValidationException::withMessages(['access_grant_id' => ['The access grant belongs to another company.']]);
            }

            if (! $grant->isUsable()) {
                throw ValidationException::withMessages(['access_grant_id' => ['This access grant is not active or has expired.']]);
            }

            $expiresAt = $grant->expires_at ?? now()->addHour();

            return [$grant, $payment, $expiresAt, (int) max(60, now()->diffInSeconds($expiresAt))];
        }

        if ($payment) {
            if ((int) $payment->company_id !== (int) $session->company_id) {
                throw ValidationException::withMessages(['payment_transaction_id' => ['The payment belongs to another company.']]);
            }

            if ($payment->status !== 'paid') {
                throw ValidationException::withMessages(['payment_transaction_id' => ['The payment has not been verified yet.']]);
            }

            $grant = AccessGrant::query()
                ->where('company_id', $session->company_id)
                ->where('payment_transaction_id', $payment->id)
                ->latest('id')
                ->first();

            if ($grant && $grant->isUsable()) {
                $expiresAt = $grant->expires_at ?? now()->addHour();

                return [$grant, $payment, $expiresAt, (int) max(60, now()->diffInSeconds($expiresAt))];
            }

            $plan = $payment->internet_plan_id
                ? InternetPlan::query()->where('company_id', $session->company_id)->find($payment->internet_plan_id)
                : null;

            $duration = $this->planDurationSeconds($plan);

            return [$grant, $payment, now()->addSeconds($duration), $duration];
        }

        throw ValidationException::withMessages([
            'payment_transaction_id' => ['A verified payment or an active access grant is required to authorize an Omada client.'],
        ]);
    }

    private function planDurationSeconds(?InternetPlan $plan): int
    {
        if (! $plan) {
            return 3600;
        }

        $duration = max(1, (int) $plan->duration);

        $seconds = match ($plan->duration_unit) {
            'HOURS' => $duration * 3600,
            'DAYS' => $duration * 86400,
            'WEEKS' => $duration * 604800,
            'MONTHS' => $duration * 2592000,
            'UNLIMITED_DATA' => (int) config('omada.max_auth_duration_seconds', 86400),
            default => 3600,
        };

        return max(60, min($seconds, (int) config('omada.max_auth_duration_seconds', 86400)));
    }

    private function normalizeMac(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');

        return strlen($hex) === 12 ? implode('-', str_split($hex, 2)) : '';
    }
}
