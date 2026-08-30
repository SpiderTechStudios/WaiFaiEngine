<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class SignupDocumentation
{
    #[OA\Post(
        path: '/signup/intents',
        operationId: 'createSignupIntent',
        tags: ['Signup'],
        summary: 'Create pay-first signup intent (no user/company yet)',
        description: 'Collects registration details and a payment phone. Backend charges only the first month subscription (config PLATFORM_SUBSCRIPTION_MONTHLY). No installation/router fee.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: [
            'first_name', 'last_name', 'business_name', 'email', 'phone', 'payment_phone',
            'password', 'password_confirmation', 'address', 'portal_subdomain',
        ], properties: [
            new OA\Property(property: 'first_name', type: 'string', example: 'Jane'),
            new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
            new OA\Property(property: 'business_name', type: 'string', example: 'ABC Internet Services'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
            new OA\Property(property: 'phone', type: 'string', example: '0700123456', description: 'Contact phone'),
            new OA\Property(property: 'payment_phone', type: 'string', example: '0711987654', description: 'Mobile money phone for first month payment'),
            new OA\Property(property: 'password', type: 'string', format: 'password'),
            new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            new OA\Property(property: 'address', type: 'string'),
            new OA\Property(property: 'portal_subdomain', type: 'string', example: 'abc-internet'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Intent created', content: new OA\JsonContent(ref: '#/components/schemas/SignupIntentCreatedResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createIntent(): void {}

    #[OA\Post(
        path: '/signup/intents/{intent}/payments',
        operationId: 'startSignupPayment',
        tags: ['Signup'],
        summary: 'Start first-month subscription mobile-money payment',
        parameters: [
            new OA\Parameter(name: 'intent', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'payment_method', type: 'string', example: 'mpesa'),
            new OA\Property(property: 'phone', type: 'string', nullable: true, description: 'Optional override; defaults to intent payment_phone'),
            new OA\Property(property: 'amount', type: 'number', nullable: true, example: 10000, description: 'Optional; must match first month fee if sent'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Payment initiated', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentCreatedResponse')),
            new OA\Response(response: 404, description: 'Intent not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function startPayment(): void {}

    #[OA\Get(
        path: '/signup/intents/{intent}/payments/{payment}',
        operationId: 'showSignupPayment',
        tags: ['Signup'],
        summary: 'Poll signup payment status',
        parameters: [
            new OA\Parameter(name: 'intent', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showPayment(): void {}

    #[OA\Post(
        path: '/signup/intents/{intent}/complete',
        operationId: 'completeSignup',
        tags: ['Signup'],
        summary: 'Complete signup after payment is paid (creates user, company, token)',
        parameters: [
            new OA\Parameter(name: 'intent', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Signup completed', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionCreatedResponse')),
            new OA\Response(response: 404, description: 'Intent not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Payment not paid or intent invalid', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function complete(): void {}
}
