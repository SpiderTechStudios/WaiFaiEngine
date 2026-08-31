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
        description: 'Creates a temporary enrollment (no user/company yet), initiates the monthly platform subscription payment, and triggers a USSD/STK push to payment_phone. Enrollment expires after PLATFORM_ENROLLMENT_TTL_MINUTES (default 4). Account creation happens only after trusted payment confirmation.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterEnrollmentRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Enrollment created and payment pending', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentStatusResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function register(): void {}

    #[OA\Get(
        path: '/auth/enrollments/{reference}/payment-status',
        operationId: 'enrollmentPaymentStatus',
        tags: ['Enrollment'],
        summary: 'Poll enrollment payment status',
        description: 'Public polling endpoint for the payment waiting screen. Does not expose password hashes or provider secrets. Returns 410 when the enrollment has expired.',
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
        description: 'Allowed while enrollment is not expired and fewer than 3 failed attempts have occurred. Optionally updates payment_phone. Does not create a new enrollment.',
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
        summary: 'Trusted platform payment provider callback',
        description: 'Authoritative payment confirmation. Requires X-Platform-Payment-Secret when PLATFORM_PAYMENT_WEBHOOK_SECRET is set. On paid enrollment payments, creates user + company + active subscription. Idempotent under replay.',
        parameters: [
            new OA\Parameter(name: 'X-Platform-Payment-Secret', in: 'header', required: false, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], properties: [
            new OA\Property(property: 'reference', type: 'string', example: 'SUB-ENR-ABC123XYZ0'),
            new OA\Property(property: 'transaction_reference', type: 'string', nullable: true),
            new OA\Property(property: 'status', type: 'string', example: 'paid', description: 'paid|failed|success|completed'),
            new OA\Property(property: 'amount', type: 'number', example: 10000),
            new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
            new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Webhook processed', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentResponse')),
            new OA\Response(response: 403, description: 'Invalid webhook secret', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function webhook(): void {}
}
