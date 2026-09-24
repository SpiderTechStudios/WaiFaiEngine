<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterEnrollmentRequest;
use App\Http\Requests\Auth\RetryEnrollmentPaymentRequest;
use App\Http\Resources\EnrollmentStatusResource;
use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Services\EnrollmentService;
use App\Services\PlatformPaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentService $enrollmentService,
        private PlatformPaymentService $platformPaymentService,
    ) {}

    public function register(RegisterEnrollmentRequest $request): JsonResponse
    {
        $result = $this->enrollmentService->createOrResume($request->validated());
        $enrollment = $result['enrollment'];

        $this->platformPaymentService->startEnrollmentPayment($enrollment, [
            'payment_phone' => $enrollment->payment_phone,
        ]);

        $enrollment->refresh();

        $message = $result['resumed']
            ? 'Existing registration found. A new payment request was sent to your phone.'
            : 'Registration submitted. Please complete the payment request on your phone.';

        return $this->success(
            (new EnrollmentStatusResource($enrollment))->resolve(),
            $message,
            $result['resumed'] ? 200 : 201,
        );
    }

    public function paymentStatus(string $reference): JsonResponse
    {
        $enrollment = $this->enrollmentService->findByReferenceOrFail($reference);

        // Even when the provider callback is delayed or missed, polling must be
        // able to confirm the charge and create the account.
        if (! $enrollment->isCompleted()) {
            $this->platformPaymentService->reconcileEnrollmentPayment($enrollment);
            $enrollment->refresh();
        }

        // Paid (or still reconciling) enrollments must not 410 — the UI needs to
        // keep polling until user/company creation finishes.
        if (
            $enrollment->status === Enrollment::STATUS_EXPIRED
            && ! $this->enrollmentStillRecoverable($enrollment)
            && ! $this->enrollmentService->isResumable($enrollment)
        ) {
            return $this->error(
                [
                    'enrollment_reference' => $enrollment->reference,
                    'enrollment_status' => 'expired',
                ],
                'The registration payment session has expired.',
                410,
            );
        }

        $message = match (true) {
            $enrollment->isCompleted() => 'Payment successful. Your account has been created.',
            $enrollment->status === Enrollment::STATUS_PAYMENT_FAILED => 'Payment was not successful. You may try again.',
            $enrollment->payments()->where('status', PlatformPayment::STATUS_PAID)->exists()
                => 'Payment confirmed. Creating your account…',
            $this->enrollmentService->isResumable($enrollment)
                => 'Waiting for payment confirmation. You can resend the payment request if you missed it.',
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

    /**
     * Expired reservation rows are still recoverable while a provider payment
     * can confirm (pending) or while a paid charge still needs account creation.
     */
    private function enrollmentStillRecoverable(Enrollment $enrollment): bool
    {
        if ($enrollment->isCompleted()) {
            return true;
        }

        $hasPaid = $enrollment->payments()
            ->where('status', PlatformPayment::STATUS_PAID)
            ->exists();

        if ($hasPaid) {
            return true;
        }

        $hasOpenPayment = $enrollment->payments()
            ->where('status', PlatformPayment::STATUS_PENDING)
            ->exists();

        if (! $hasOpenPayment || ! $enrollment->expires_at) {
            return false;
        }

        return $this->enrollmentService->withinPaymentGrace($enrollment);
    }

    public function retryPayment(RetryEnrollmentPaymentRequest $request, string $reference): JsonResponse
    {
        $enrollment = $this->enrollmentService->findByReferenceOrFail($reference);

        try {
            $this->platformPaymentService->retryEnrollmentPayment($enrollment, $request->validated());
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 410) {
                return $this->error(
                    [
                        'enrollment_reference' => $enrollment->reference,
                        'enrollment_status' => $enrollment->fresh()->status,
                    ],
                    $e->getMessage() ?: 'The registration payment session has expired.',
                    410,
                );
            }

            throw $e;
        }

        $enrollment->refresh();

        return $this->success(
            (new EnrollmentStatusResource($enrollment))->resolve(),
            'Payment request resent. Please complete the payment on your phone.',
        );
    }
}
