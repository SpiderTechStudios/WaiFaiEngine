<?php

namespace App\Http\Resources;

use App\Models\CaptiveSession;
use App\Services\CaptiveSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $captiveToken = (string) data_get($this->metadata, 'captive_session', '');
        $gatewayAuthUrl = null;
        $captiveStatus = null;

        if ($captiveToken !== '' && $this->status === 'paid') {
            $captive = app(CaptiveSessionService::class)->findByToken($captiveToken);
            if ($captive instanceof CaptiveSession && (int) $captive->company_id === (int) $this->company_id) {
                $captiveStatus = $captive->status;
                if ($captive->isAuthenticated()) {
                    $gatewayAuthUrl = app(CaptiveSessionService::class)->gatewayAuthRedirectUrl($captive);
                }
            }
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'provider' => data_get($this->metadata, 'provider'),
            'provider_reference' => $this->external_reference,
            'platform_payment_reference' => data_get($this->metadata, 'platform_payment_reference'),
            'ussd_message' => data_get($this->metadata, 'provider_charge.message'),
            'package' => $this->whenLoaded('internetPlan', fn () => $this->internetPlan ? [
                'id' => $this->internetPlan->id,
                'name' => $this->internetPlan->name,
                'price' => $this->internetPlan->price,
            ] : null),
            'captive_status' => $captiveStatus,
            'gateway_auth_url' => $gatewayAuthUrl,
            'next_action' => $this->resolveNextAction($gatewayAuthUrl, $captiveStatus),
        ];
    }

    private function resolveNextAction(?string $gatewayAuthUrl, ?string $captiveStatus): string
    {
        if ($this->status === 'paid' && filled($gatewayAuthUrl)) {
            return 'open_gateway_auth_url';
        }

        if ($this->status === 'paid' && $captiveStatus === CaptiveSession::STATUS_AUTHENTICATED) {
            return 'gateway_auth_unavailable';
        }

        if ($this->status === 'paid') {
            return 'start_session';
        }

        if ($this->status === 'failed') {
            return 'retry_payment';
        }

        return 'poll_payment';
    }
}
