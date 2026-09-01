<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class InstallationDocumentation
{
    #[OA\Get(
        path: '/installation-requests',
        operationId: 'listInstallationRequests',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'List installation requests for the current company',
        description: 'Requires active subscription and installation_requests.view permission.',
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List', content: new OA\JsonContent(ref: '#/components/schemas/InstallationRequestListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden or inactive subscription', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/installation-requests',
        operationId: 'createInstallationRequest',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Create installation / router supply request',
        description: 'Backend calculates unit_price and total_amount from config. Client must not send prices. Creates one item row per quantity. Payment and fulfillment start as pending/requested.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['service_type', 'quantity'], properties: [
            new OA\Property(property: 'service_type', type: 'string', enum: ['installation_only', 'router_and_installation'], description: 'installation_only = TZS 100000/router; router_and_installation = TZS 150000/router'),
            new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 100, example: 2),
            new OA\Property(property: 'customer_notes', type: 'string', nullable: true, maxLength: 2000),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/InstallationRequestCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden or inactive subscription', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function create(): void {}

    #[OA\Get(
        path: '/installation-requests/{installationRequest}',
        operationId: 'showInstallationRequest',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Installation request details with progress tracker',
        description: 'Customer-visible updates only (internal notes omitted). Includes progress steps for UI tracker.',
        parameters: [
            new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Details', content: new OA\JsonContent(ref: '#/components/schemas/InstallationRequestResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 403, description: 'Forbidden or inactive subscription', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/installation-requests/{installationRequest}/payments',
        operationId: 'startInstallationPayment',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Start payment for an installation request',
        description: 'Amount must match the request total_amount snapshot. Creates a platform payment of type installation.',
        parameters: [
            new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'mobile_money'),
            new OA\Property(property: 'phone', type: 'string', nullable: true),
            new OA\Property(property: 'amount', type: 'number', nullable: true, description: 'Optional; must match request total if sent'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Payment initiated', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentCreatedResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function startPayment(): void {}

    #[OA\Get(
        path: '/installation-requests/{installationRequest}/payments/{payment}',
        operationId: 'showInstallationPayment',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Poll installation payment status',
        parameters: [
            new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPaymentResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showPayment(): void {}

    #[OA\Get(
        path: '/admin/installation-requests',
        operationId: 'adminListInstallationRequests',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Platform admin: list installation requests across tenants',
        parameters: [
            new OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fulfillment_status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'payment_status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List', content: new OA\JsonContent(ref: '#/components/schemas/InstallationRequestListResponse')),
            new OA\Response(response: 403, description: 'Platform admin required', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function adminIndex(): void {}

    #[OA\Get(
        path: '/admin/installation-requests/{installationRequest}',
        operationId: 'adminShowInstallationRequest',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Platform admin: request details including internal updates',
        parameters: [
            new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Details', content: new OA\JsonContent(ref: '#/components/schemas/InstallationRequestResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function adminShow(): void {}

    #[OA\Patch(
        path: '/admin/installation-requests/{installationRequest}/fulfillment',
        operationId: 'adminUpdateInstallationFulfillment',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Platform admin: update fulfillment status',
        description: 'Payment must be paid before advancing (except cancelled). Records status history.',
        parameters: [
            new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['fulfillment_status'], properties: [
            new OA\Property(property: 'fulfillment_status', type: 'string', enum: ['requested', 'processing', 'on_site', 'delivered', 'active', 'cancelled']),
            new OA\Property(property: 'note', type: 'string', nullable: true, maxLength: 2000),
            new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/InstallationRequestResponse')),
            new OA\Response(response: 422, description: 'Validation error / unpaid', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function adminUpdateFulfillment(): void {}

    #[OA\Post(
        path: '/admin/installation-requests/{installationRequest}/updates',
        operationId: 'adminAddInstallationUpdate',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Platform admin: add customer-visible or internal update',
        parameters: [
            new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['visibility', 'body'], properties: [
            new OA\Property(property: 'visibility', type: 'string', enum: ['customer', 'internal']),
            new OA\Property(property: 'body', type: 'string', maxLength: 5000),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Update added', content: new OA\JsonContent(ref: '#/components/schemas/InstallationUpdateCreatedResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function adminAddUpdate(): void {}
}
