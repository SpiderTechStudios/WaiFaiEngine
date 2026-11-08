<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformPaymentResource;
use App\Models\PaymentProvider;
use App\Payments\PaymentProviderManager;
use App\Services\PlatformPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentProviderWebhookController extends Controller
{
    public function __construct(private PlatformPaymentService $platformPaymentService) {}

    public function payments(Request $request, string $provider): JsonResponse|Response
    {
        $payment = $this->platformPaymentService->handleProviderWebhook(
            $provider,
            $request->all(),
            $request->headers->all(),
            $request->getContent() ?: null,
        );

        // PalmPay requires a plain-text "success" body or it retries the notifyUrl.
        if ($this->isPalmPayProvider($provider)) {
            return response('success', 200)->header('Content-Type', 'text/plain');
        }

        return $this->success(
            (new PlatformPaymentResource($payment))->resolve(),
            'Payment webhook processed',
        );
    }

    public function payouts(Request $request, string $provider): JsonResponse|Response
    {
        // Payout completion handlers will use the same provider validation pattern.
        // For now acknowledge verified payout callbacks without mixing into collections.
        $driver = app(PaymentProviderManager::class)->driverBySlug($provider);

        if (! $driver->validateWebhook($request->headers->all(), $request->all(), $request->getContent() ?: null)) {
            abort(401, 'Invalid payout provider webhook signature.');
        }

        $parsed = $driver->parseWebhook($request->all());

        Log::info('payment_provider_payout_webhook_received', [
            'provider' => $provider,
            'status' => $parsed->status,
            'reference' => $parsed->txRef,
            'provider_reference' => $parsed->providerReference,
            'payload_keys' => array_keys($request->all()),
        ]);

        if ($this->isPalmPayProvider($provider)) {
            return response('success', 200)->header('Content-Type', 'text/plain');
        }

        return $this->success([
            'provider' => $provider,
            'accepted' => true,
            'status' => $parsed->status,
            'reference' => $parsed->txRef,
            'provider_reference' => $parsed->providerReference,
        ], 'Payout webhook accepted');
    }

    private function isPalmPayProvider(string $providerSlug): bool
    {
        if ($providerSlug === PaymentProvider::SLUG_PALMPAY) {
            return true;
        }

        $provider = PaymentProvider::query()->where('slug', $providerSlug)->first();

        return $provider !== null
            && (string) $provider->setting('driver', $provider->slug) === PaymentProvider::SLUG_PALMPAY;
    }
}
