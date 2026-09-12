<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\TestProviderPaymentRequest;
use App\Models\PaymentProvider;
use App\Services\PaymentProviderService;
use Illuminate\Http\JsonResponse;

class TestPaymentController extends Controller
{
    public function __construct(private PaymentProviderService $paymentProviderService) {}

    /**
     * Fire a live provider collection (e.g. PalmPesa USSD) without creating a platform_payments row.
     */
    public function testCollection(TestProviderPaymentRequest $request, string $provider): JsonResponse
    {
        $paymentProvider = PaymentProvider::findBySlugOrFail($provider);

        $result = $this->paymentProviderService->testCollection(
            $paymentProvider,
            $request->validated(),
        );

        return $this->success($result, 'Provider test payment response');
    }
}
