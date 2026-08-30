<?php

namespace App\Http\Controllers\Api\V1\Signup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signup\StoreSignupIntentRequest;
use App\Http\Requests\Signup\StoreSignupPaymentRequest;
use App\Http\Resources\AuthSessionResource;
use App\Http\Resources\PlatformPaymentResource;
use App\Http\Resources\SignupIntentResource;
use App\Services\PlatformPaymentService;
use App\Services\SignupCompletionService;
use App\Services\SignupIntentService;
use Illuminate\Http\JsonResponse;

class SignupController extends Controller
{
    public function __construct(
        private SignupIntentService $signupIntentService,
        private PlatformPaymentService $platformPaymentService,
        private SignupCompletionService $signupCompletionService,
    ) {}

    public function storeIntent(StoreSignupIntentRequest $request): JsonResponse
    {
        $intent = $this->signupIntentService->create($request->validated());

        return $this->success(
            (new SignupIntentResource($intent))->resolve(),
            'Signup intent created',
            201,
        );
    }

    public function storePayment(StoreSignupPaymentRequest $request, string $intent): JsonResponse
    {
        $signupIntent = $this->signupIntentService->findActiveOrFail($intent);
        $payment = $this->platformPaymentService->startSignupPayment(
            $signupIntent,
            $request->validated(),
        );

        return $this->success(
            (new PlatformPaymentResource($payment))->resolve(),
            'Signup payment initiated',
            201,
        );
    }

    public function showPayment(string $intent, int $payment): JsonResponse
    {
        $signupIntent = $this->signupIntentService->findActiveOrFail($intent);
        $paymentModel = $this->platformPaymentService->findForIntentOrFail($signupIntent, $payment);

        return $this->success(
            (new PlatformPaymentResource($paymentModel))->resolve(),
            'Signup payment retrieved',
        );
    }

    public function complete(string $intent): JsonResponse
    {
        $signupIntent = $this->signupIntentService->findActiveOrFail($intent);
        $payload = $this->signupCompletionService->complete($signupIntent);

        return $this->success(
            (new AuthSessionResource($payload))->resolve(),
            'Signup completed',
            201,
        );
    }
}
