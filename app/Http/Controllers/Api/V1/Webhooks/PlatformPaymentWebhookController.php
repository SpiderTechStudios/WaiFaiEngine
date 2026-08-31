<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\PlatformPaymentWebhookRequest;
use App\Http\Resources\PlatformPaymentResource;
use App\Services\PlatformPaymentService;
use Illuminate\Http\JsonResponse;

class PlatformPaymentWebhookController extends Controller
{
    public function __construct(private PlatformPaymentService $platformPaymentService) {}

    public function __invoke(PlatformPaymentWebhookRequest $request): JsonResponse
    {
        $payment = $this->platformPaymentService->handleProviderCallback($request->validated());

        return $this->success(
            (new PlatformPaymentResource($payment))->resolve(),
            'Payment webhook processed',
        );
    }
}
