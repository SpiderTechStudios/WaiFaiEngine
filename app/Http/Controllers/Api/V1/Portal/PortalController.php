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
use App\Support\PortalContext;
use App\Services\NetworkSessionService;
use App\Services\PaymentService;
use App\Services\PortalService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;

class PortalController extends Controller
{
    public function __construct(
        private PortalContext $portalContext,
        private PortalService $portalService,
        private PaymentService $paymentService,
        private VoucherService $voucherService,
        private NetworkSessionService $networkSessionService,
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
        $result = $this->voucherService->redeemByCode(
            $this->portalCompany(),
            $request->validated(),
        );

        $grant = $result['access_grant'];
        $plan = $result['voucher']->internetPlan;

        return $this->success([
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
        ], 'Voucher redeemed', 201);
    }

    public function createSession(PortalStoreSessionRequest $request): JsonResponse
    {
        $session = $this->networkSessionService->create(
            $this->portalCompany(),
            $request->validated(),
        );

        return $this->success(
            (new NetworkSessionResource($session))->resolve(),
            'Session started',
            201,
        );
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
