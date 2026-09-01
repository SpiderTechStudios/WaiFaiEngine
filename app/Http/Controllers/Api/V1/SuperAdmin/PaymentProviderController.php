<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StorePaymentProviderRequest;
use App\Http\Requests\SuperAdmin\UpdatePaymentProviderRequest;
use App\Http\Resources\PaymentProviderResource;
use App\Models\PaymentProvider;
use App\Services\PaymentProviderService;
use Illuminate\Http\JsonResponse;

class PaymentProviderController extends Controller
{
    public function __construct(private PaymentProviderService $paymentProviderService) {}

    public function index(): JsonResponse
    {
        $providers = PaymentProvider::query()->orderBy('name')->get();

        return $this->success(
            PaymentProviderResource::collection($providers)->resolve(),
            'Payment providers retrieved',
        );
    }

    public function store(StorePaymentProviderRequest $request): JsonResponse
    {
        $provider = $this->paymentProviderService->create($request->validated());

        return $this->success(
            (new PaymentProviderResource($provider))->resolve(),
            'Payment provider created',
            201,
        );
    }

    public function show(PaymentProvider $paymentProvider): JsonResponse
    {
        return $this->success(
            (new PaymentProviderResource($paymentProvider))->resolve(),
            'Payment provider retrieved',
        );
    }

    public function update(UpdatePaymentProviderRequest $request, PaymentProvider $paymentProvider): JsonResponse
    {
        $provider = $this->paymentProviderService->update($paymentProvider, $request->validated());

        return $this->success(
            (new PaymentProviderResource($provider))->resolve(),
            'Payment provider updated',
        );
    }

    public function destroy(PaymentProvider $paymentProvider): JsonResponse
    {
        $this->paymentProviderService->delete($paymentProvider);

        return $this->success([], 'Payment provider deleted or deactivated');
    }

    public function enable(PaymentProvider $paymentProvider): JsonResponse
    {
        $provider = $this->paymentProviderService->enable($paymentProvider);

        return $this->success(
            (new PaymentProviderResource($provider))->resolve(),
            'Payment provider enabled',
        );
    }

    public function disable(PaymentProvider $paymentProvider): JsonResponse
    {
        $provider = $this->paymentProviderService->disable($paymentProvider);

        return $this->success(
            (new PaymentProviderResource($provider))->resolve(),
            'Payment provider disabled',
        );
    }

    public function setDefaultPayments(PaymentProvider $paymentProvider): JsonResponse
    {
        $provider = $this->paymentProviderService->setDefaultForPayments($paymentProvider);

        return $this->success(
            (new PaymentProviderResource($provider))->resolve(),
            'Default payment provider updated',
        );
    }

    public function setDefaultPayouts(PaymentProvider $paymentProvider): JsonResponse
    {
        $provider = $this->paymentProviderService->setDefaultForPayouts($paymentProvider);

        return $this->success(
            (new PaymentProviderResource($provider))->resolve(),
            'Default payout provider updated',
        );
    }
}
