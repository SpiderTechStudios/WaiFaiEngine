<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPaymentResource;
use App\Services\PlatformPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentProviderWebhookController extends Controller
{
    public function __construct(private PlatformPaymentService $platformPaymentService) {}

    public function payments(Request $request, string $provider): JsonResponse
    {
        $payment = $this->platformPaymentService->handleProviderWebhook(
            $provider,
            $request->all(),
            $request->headers->all(),
            $request->getContent() ?: null,
        );

        return $this->success(
            (new PlatformPaymentResource($payment))->resolve(),
            'Payment webhook processed',
        );
    }

    public function payouts(Request $request, string $provider): JsonResponse
    {
        // Payout completion handlers will use the same provider validation pattern.
        // For now acknowledge verified payout callbacks without mixing into collections.
        $driver = app(\App\Payments\PaymentProviderManager::class)->driverBySlug($provider);

        if (! $driver->validateWebhook($request->headers->all(), $request->all(), $request->getContent() ?: null)) {
            abort(401, 'Invalid payout provider webhook signature.');
        }

        return $this->success([
            'provider' => $provider,
            'accepted' => true,
        ], 'Payout webhook accepted');
    }
}
