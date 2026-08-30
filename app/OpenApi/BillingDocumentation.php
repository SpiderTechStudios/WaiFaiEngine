<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class BillingDocumentation
{
    #[OA\Get(
        path: '/billing/subscription',
        operationId: 'getSubscription',
        tags: ['Billing'],
        security: [['sanctum' => []]],
        summary: 'Current company subscription status',
        description: 'Available even when subscription is expired so the tenant can renew. Does not require subscription.active middleware.',
        responses: [
            new OA\Response(response: 200, description: 'Subscription', content: new OA\JsonContent(ref: '#/components/schemas/SubscriptionResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'No company selected', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function subscription(): void {}

    #[OA\Post(
        path: '/billing/subscription/payments',
        operationId: 'startSubscriptionRenewal',
        tags: ['Billing'],
        security: [['sanctum' => []]],
        summary: 'Start monthly subscription renewal payment',
        description: 'Creates a platform payment of type subscription_renewal for PLATFORM_SUBSCRIPTION_MONTHLY. Amount is server-side; client amount is optional and must match if sent. When payment is marked paid, subscription period is extended by one month.',
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'mpesa'),
            new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0711987654'),
            new OA\Property(property: 'amount', type: 'number', nullable: true, example: 10000, description: 'Optional; must equal configured monthly fee'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Payment initiated', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function renew(): void {}

    #[OA\Get(
        path: '/billing/subscription/payments/{payment}',
        operationId: 'showSubscriptionPayment',
        tags: ['Billing'],
        security: [['sanctum' => []]],
        summary: 'Poll subscription renewal payment status',
        parameters: [
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showPayment(): void {}
}
