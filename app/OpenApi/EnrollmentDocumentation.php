<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class EnrollmentDocumentation
{
    #[OA\Post(
        path: '/auth/register',
        operationId: 'registerEnrollment',
        tags: ['Enrollment'],
        summary: 'Start pay-first registration enrollment',
        description: 'Creates a temporary enrollment (no user/company yet), creates a platform_subscription payment intent on the default collection provider, and initiates USSD/STK. If the same email already has an incomplete enrollment that can still accept payment, that enrollment is resumed and a new USSD/STK push is sent instead of rejecting the request. Enrollment expires after PLATFORM_ENROLLMENT_TTL_MINUTES (default 4). Account creation happens only after a verified provider webhook.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterEnrollmentRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Enrollment created; payment pending', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentStatusResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function register(): void {}

    #[OA\Get(
        path: '/auth/enrollments/{reference}/payment-status',
        operationId: 'enrollmentPaymentStatus',
        tags: ['Enrollment'],
        summary: 'Poll enrollment payment status',
        description: 'Public polling endpoint for the payment waiting screen. Does not expose password hashes or provider secrets. status=false with HTTP 200 when payment_failed (retry allowed). HTTP 410 when expired.',
        parameters: [
            new OA\Parameter(name: 'reference', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'ENR-ABC123XYZ')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pending, failed (retryable), or completed', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentStatusResponse')),
            new OA\Response(response: 404, description: 'Unknown reference', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 410, description: 'Enrollment expired', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentExpiredResponse')),
        ]
    )]
    public function paymentStatus(): void {}

    #[OA\Post(
        path: '/auth/enrollments/{reference}/retry-payment',
        operationId: 'retryEnrollmentPayment',
        tags: ['Enrollment'],
        summary: 'Retry enrollment platform subscription payment',
        description: 'Allowed while the enrollment can still accept payment (active or within the post-expiry grace window) and fewer than 3 failed attempts have occurred. Optionally updates payment_phone. Reuses the same enrollment, extends the reservation TTL, cancels any pending charge, and initiates a new USSD/STK push.',
        parameters: [
            new OA\Parameter(name: 'reference', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'ENR-ABC123XYZ')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/RetryEnrollmentPaymentRequest')),
        responses: [
            new OA\Response(response: 200, description: 'New payment request sent', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentStatusResponse')),
            new OA\Response(response: 410, description: 'Expired or max attempts reached', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentExpiredResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function retryPayment(): void {}

    #[OA\Post(
        path: '/webhooks/platform-payments',
        operationId: 'platformPaymentWebhook',
        tags: ['Enrollment'],
        summary: 'Deprecated legacy webhook — use POST /webhooks/payments/{provider}',
        description: 'Kept for compatibility. Prefer provider-scoped webhooks. Requires X-Platform-Payment-Secret for the stub provider.',
        deprecated: true,
        parameters: [
            new OA\Parameter(name: 'X-Platform-Payment-Secret', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StubPaymentWebhookRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Webhook processed', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentResponse')),
            new OA\Response(response: 401, description: 'Invalid webhook secret', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function webhook(): void {}
}
