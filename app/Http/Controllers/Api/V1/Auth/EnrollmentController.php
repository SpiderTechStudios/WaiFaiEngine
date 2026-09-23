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

        $graceMinutes = max(0, (int) config('platform.enrollment_payment_grace_minutes', 60));

        return now()->lessThanOrEqualTo(
            $enrollment->expires_at->copy()->addMinutes($graceMinutes)
        );
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
