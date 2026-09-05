<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalRedeemVoucherRequest;
use App\Http\Requests\Portal\PortalRestoreRequest;
use App\Http\Requests\Portal\PortalStorePaymentRequest;
use App\Http\Requests\Portal\PortalStoreSessionRequest;
use App\Http\Resources\NetworkSessionResource;
use App\Http\Resources\PortalPaymentResource;
use App\Models\Company;
use App\Models\PaymentTransaction;
use App\Services\CaptiveSessionService;
use App\Services\NetworkSessionService;
use App\Services\PaymentService;
use App\Services\PortalService;
use App\Services\VoucherService;
use App\Support\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function __construct(
        private PortalContext $portalContext,
        private PortalService $portalService,
        private PaymentService $paymentService,
        private VoucherService $voucherService,
        private NetworkSessionService $networkSessionService,
        private CaptiveSessionService $captiveSessionService,
    ) {}

    public function bootstrap(): JsonResponse
    {
        return $this->success(
            $this->portalService->bootstrap($this->portalCompany()),
            'Portal bootstrap',
        );
    }

    public function createPayment(PortalStorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->createForPortal(
            $this->portalCompany(),
            $request->validated(),
        );

        return $this->success(
            (new PortalPaymentResource($payment))->resolve(),
            'Payment initiated',
            201,
        );
    }

    public function showPayment(string $subdomain, int $payment): JsonResponse
    {
        $paymentModel = PaymentTransaction::query()
            ->with('internetPlan')
            ->where('company_id', $this->portalCompany()->id)
            ->findOrFail($payment);

        return $this->success(
            (new PortalPaymentResource($paymentModel))->resolve(),
            'Payment retrieved',
        );
    }

    public function redeemVoucher(PortalRedeemVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->voucherService->redeemByCode(
            $this->portalCompany(),
            $validated,
        );

        $grant = $result['access_grant'];
        $plan = $result['voucher']->internetPlan;

        $payload = [
            'customer' => [
                'id' => $result['customer']->id,
                'name' => $result['customer']->name,
                'phone' => $result['customer']->phone,
            ],
            'access_grant' => [
                'id' => $grant->id,
                'status' => $grant->status,
                'source' => $grant->source,
                'starts_at' => $grant->starts_at,
                'expires_at' => $grant->expires_at,
            ],
            'package' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'duration' => $plan->duration,
                'duration_unit' => $plan->duration_unit,
            ] : null,
        ];

        if (! empty($validated['captive_session'])) {
            $payload['captive'] = $this->authorizeCaptiveSession(
                (string) $validated['captive_session'],
                [
                    'access_grant_id' => $grant->id,
                    'mac_address' => $validated['mac_address'] ?? null,
                ],
            );
        }

        return $this->success($payload, 'Voucher redeemed', 201);
    }

    public function createSession(PortalStoreSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $captiveToken = $validated['captive_session'] ?? null;
        unset($validated['captive_session']);

        if ($captiveToken) {
            $captive = $this->captiveSessionService->findByToken((string) $captiveToken);
            if (! $captive || (int) $captive->company_id !== (int) $this->portalCompany()->id) {
                throw ValidationException::withMessages([
                    'captive_session' => ['Captive session not found for this portal.'],
                ]);
            }

            $validated['router_id'] = $validated['router_id'] ?? $captive->network_device_id;
            $validated['network_station_id'] = $validated['network_station_id'] ?? $captive->network_station_id;
            $validated['ip_address'] = $validated['ip_address'] ?? $captive->client_ip;
            $validated['mac_address'] = $validated['mac_address'] ?? $captive->client_mac;
            $validated['session_id'] = $validated['session_id'] ?? $captive->token;
        }

        $session = $this->networkSessionService->create(
            $this->portalCompany(),
            $validated,
        );

        $payload = (new NetworkSessionResource($session))->resolve();

        if ($captiveToken) {
            $captive = $this->captiveSessionService->findByToken((string) $captiveToken);
            $captive = $this->captiveSessionService->linkHotspotSession($captive, $session);
            $payload['captive'] = [
                ...$this->captiveSessionService->toPublicArray($captive),
                'gateway_auth_url' => $this->captiveSessionService->gatewayAuthRedirectUrl($captive),
            ];
        }

        return $this->success($payload, 'Session started', 201);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function authorizeCaptiveSession(string $token, array $data): array
    {
        $captive = $this->captiveSessionService->findByToken($token);

        if (! $captive || (int) $captive->company_id !== (int) $this->portalCompany()->id) {
            throw ValidationException::withMessages([
                'captive_session' => ['Captive session not found for this portal.'],
            ]);
        }

        if ($captive->isAuthenticated()) {
            return [
                ...$this->captiveSessionService->toPublicArray($captive),
                'gateway_auth_url' => $this->captiveSessionService->gatewayAuthRedirectUrl($captive),
            ];
        }

        $captive = $this->captiveSessionService->authenticate($captive, $data);

        return [
            ...$this->captiveSessionService->toPublicArray($captive),
            'gateway_auth_url' => $this->captiveSessionService->gatewayAuthRedirectUrl($captive),
        ];
    }

    public function restore(PortalRestoreRequest $request): JsonResponse
    {
        return $this->success(
            $this->portalService->restore($this->portalCompany(), $request->validated()),
            'Access restored',
        );
    }

    private function portalCompany(): Company
    {
        $company = $this->portalContext->company;

        if (! $company) {
            abort(404, 'Portal not found.');
        }

        return $company;
    }
}
