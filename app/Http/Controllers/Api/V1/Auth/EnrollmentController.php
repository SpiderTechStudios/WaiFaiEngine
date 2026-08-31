<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterEnrollmentRequest;
use App\Http\Requests\Auth\RetryEnrollmentPaymentRequest;
use App\Http\Resources\EnrollmentStatusResource;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use App\Services\PlatformPaymentService;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentService $enrollmentService,
        private PlatformPaymentService $platformPaymentService,
    ) {}

    public function register(RegisterEnrollmentRequest $request): JsonResponse
    {
        $enrollment = $this->enrollmentService->create($request->validated());
        $payment = $this->platformPaymentService->startEnrollmentPayment($enrollment);

        $enrollment->refresh();

        return $this->success(
            (new EnrollmentStatusResource($enrollment))->resolve(),
            'Registration submitted. Please complete the payment request on your phone.',
            201,
        );
    }

    public function paymentStatus(string $reference): JsonResponse
    {
        $enrollment = $this->enrollmentService->findByReferenceOrFail($reference);

        if ($enrollment->status === Enrollment::STATUS_EXPIRED) {
            return $this->error(
                [
                    'enrollment_reference' => $enrollment->reference,
                    'enrollment_status' => 'expired',
                ],
                'The registration payment session has expired.',
                410,
            );
        }

        $message = match ($enrollment->status) {
            Enrollment::STATUS_COMPLETED => 'Payment successful. Your account has been created.',
            Enrollment::STATUS_PAYMENT_FAILED => 'Payment was not successful. You may try again.',
            default => 'Waiting for payment confirmation.',
        };

        $statusOk = $enrollment->status !== Enrollment::STATUS_PAYMENT_FAILED;

        return response()->json([
            'status' => $statusOk,
            'code' => 200,
            'message' => $message,
            'data' => (new EnrollmentStatusResource($enrollment))->resolve(),
        ], 200);
    }

    public function retryPayment(RetryEnrollmentPaymentRequest $request, string $reference): JsonResponse
    {
        $enrollment = $this->enrollmentService->findByReferenceOrFail($reference);

        if ($enrollment->status === Enrollment::STATUS_EXPIRED) {
            return $this->error(
                [
                    'enrollment_reference' => $enrollment->reference,
                    'enrollment_status' => 'expired',
                ],
                'The registration payment session has expired.',
                410,
            );
        }

        $this->platformPaymentService->retryEnrollmentPayment($enrollment, $request->validated());
        $enrollment->refresh();

        return $this->success(
            (new EnrollmentStatusResource($enrollment))->resolve(),
            'Payment request resent. Please complete the payment on your phone.',
        );
    }
}
