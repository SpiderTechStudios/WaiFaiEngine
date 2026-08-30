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
        responses: [
            new OA\Response(response: 200, description: 'Subscription'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function subscription(): void {}

    #[OA\Post(
        path: '/billing/subscription/payments',
        operationId: 'startSubscriptionRenewal',
        tags: ['Billing'],
        security: [['sanctum' => []]],
        summary: 'Start monthly subscription renewal payment',
        responses: [
            new OA\Response(response: 201, description: 'Payment initiated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function renew(): void {}
}
