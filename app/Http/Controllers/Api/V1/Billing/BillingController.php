<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPaymentResource;
use App\Services\PlatformPaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private PlatformPaymentService $platformPaymentService,
    ) {}

    public function subscription(): JsonResponse
    {
        return $this->success(
            $this->subscriptionService->summary($this->currentCompany()),
            'Subscription retrieved',
        );
    }

    public function startRenewal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payment = $this->platformPaymentService->startRenewalPayment(
            $this->currentCompany(),
            $data,
        );

        return $this->success(
            (new PlatformPaymentResource($payment))->resolve(),
            'Subscription renewal payment initiated',
            201,
        );
    }

    public function showPayment(int $payment): JsonResponse
    {
        $paymentModel = $this->platformPaymentService->findForCompanyOrFail(
            $this->currentCompany(),
            $payment,
        );

        return $this->success(
            (new PlatformPaymentResource($paymentModel))->resolve(),
            'Platform payment retrieved',
        );
    }
}
