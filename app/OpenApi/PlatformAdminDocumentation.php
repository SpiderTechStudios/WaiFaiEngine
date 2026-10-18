<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class PlatformAdminDocumentation
{
    #[OA\Get(
        path: '/admin/dashboard',
        operationId: 'adminDashboard',
        tags: ['Platform Admin'],
        summary: 'Platform-wide admin dashboard KPIs',
        description: 'Aggregates today/total revenue, payments, active sessions, router online/offline counts, recent sessions, top plans by recent usage, and last-14-days revenue for the admin home screen.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Admin dashboard', content: new OA\JsonContent(ref: '#/components/schemas/AdminDashboardResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function dashboard(): void {}

    #[OA\Get(
        path: '/admin/routers',
        operationId: 'adminListRouters',
        tags: ['Platform Admin'],
        summary: 'List routers for all clients',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'gateway_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['mikrotik', 'ruijie', 'wavlink'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'offline'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Routers', content: new OA\JsonContent(ref: '#/components/schemas/AdminRouterListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listRouters(): void {}

    #[OA\Post(
        path: '/admin/routers',
        operationId: 'adminCreateRouter',
        tags: ['Platform Admin'],
        summary: 'Add a router for a client company',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['company_id', 'gateway_type', 'name'], properties: [
            new OA\Property(property: 'company_id', type: 'integer', example: 1),
            new OA\Property(property: 'gateway_type', type: 'string', enum: ['mikrotik', 'ruijie', 'wavlink'], example: 'mikrotik'),
            new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot'),
            new OA\Property(property: 'lan_ip', type: 'string', nullable: true, example: '192.168.88.1'),
            new OA\Property(property: 'api_host', type: 'string', nullable: true, example: '41.59.12.34'),
            new OA\Property(property: 'api_port', type: 'integer', nullable: true, example: 443),
            new OA\Property(property: 'api_username', type: 'string', nullable: true),
            new OA\Property(property: 'api_password', type: 'string', nullable: true),
            new OA\Property(property: 'gateway_id', type: 'string', nullable: true, example: 'G1UQCC8000976'),
            new OA\Property(property: 'serial_number', type: 'string', nullable: true),
            new OA\Property(property: 'wifidog_port', type: 'integer', nullable: true, example: 2060),
            new OA\Property(property: 'branch_id', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/AdminRouterCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createRouter(): void {}

    #[OA\Get(
        path: '/admin/routers/{router}',
        operationId: 'adminShowRouter',
        tags: ['Platform Admin'],
        summary: 'Get router and client company details',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Router and company', content: new OA\JsonContent(ref: '#/components/schemas/AdminRouterDetailResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showRouter(): void {}

    #[OA\Patch(
        path: '/admin/routers/{router}',
        operationId: 'adminUpdateRouter',
        tags: ['Platform Admin'],
        summary: 'Update a client router',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'company_id', type: 'integer', example: 1),
            new OA\Property(property: 'gateway_type', type: 'string', enum: ['mikrotik', 'ruijie', 'wavlink'], example: 'mikrotik'),
            new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot'),
            new OA\Property(property: 'lan_ip', type: 'string', nullable: true, example: '192.168.88.1'),
            new OA\Property(property: 'api_host', type: 'string', nullable: true, example: '41.59.12.34'),
            new OA\Property(property: 'api_port', type: 'integer', nullable: true, example: 443),
            new OA\Property(property: 'api_username', type: 'string', nullable: true),
            new OA\Property(property: 'api_password', type: 'string', nullable: true),
            new OA\Property(property: 'gateway_id', type: 'string', nullable: true, example: 'G1UQCC8000976'),
            new OA\Property(property: 'serial_number', type: 'string', nullable: true),
            new OA\Property(property: 'wifidog_port', type: 'integer', nullable: true, example: 2060),
            new OA\Property(property: 'branch_id', type: 'integer', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'offline'], nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/AdminRouterResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function updateRouter(): void {}

    #[OA\Delete(
        path: '/admin/routers/{router}',
        operationId: 'adminDeleteRouter',
        tags: ['Platform Admin'],
        summary: 'Delete a client router',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function deleteRouter(): void {}

    #[OA\Post(
        path: '/admin/routers/{router}/sync',
        operationId: 'adminSyncRouter',
        tags: ['Platform Admin'],
        summary: 'Sync a Ruijie router from Ruijie Cloud for a client',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Synced', content: new OA\JsonContent(ref: '#/components/schemas/AdminRouterResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Not a Ruijie router or credentials missing', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 502, description: 'Ruijie Cloud sync failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function syncRouter(): void {}

    #[OA\Get(
        path: '/admin/billing/summary',
        operationId: 'adminBillingSummary',
        tags: ['Platform Admin'],
        summary: 'Platform revenue and subscription-health overview',
        description: 'What client companies pay WaiFai (subscriptions, renewals, installation, hardware) plus MRR, client status counts, due-soon and overdue subscriptions.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, description: 'Y-m-d, default start of current month', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, description: 'Y-m-d, default today', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Platform billing summary retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
                    new OA\Property(property: 'monthly_fee', type: 'number', example: 20000),
                    new OA\Property(property: 'mrr', type: 'number', example: 60000, description: 'monthly fee × active clients'),
                    new OA\Property(property: 'clients', type: 'object', properties: [
                        new OA\Property(property: 'total', type: 'integer', example: 4),
                        new OA\Property(property: 'active', type: 'integer', example: 3),
                        new OA\Property(property: 'past_due', type: 'integer', example: 0),
                        new OA\Property(property: 'expired', type: 'integer', example: 1),
                        new OA\Property(property: 'suspended', type: 'integer', example: 0),
                        new OA\Property(property: 'cancelled', type: 'integer', example: 0),
                    ]),
                    new OA\Property(property: 'due_soon', type: 'object', properties: [
                        new OA\Property(property: 'days', type: 'integer', example: 7),
                        new OA\Property(property: 'count', type: 'integer', example: 1),
                        new OA\Property(property: 'amount', type: 'number', example: 20000),
                    ]),
                    new OA\Property(property: 'overdue', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 1),
                        new OA\Property(property: 'amount', type: 'number', example: 20000),
                    ]),
                    new OA\Property(property: 'revenue', type: 'object', properties: [
                        new OA\Property(property: 'total', type: 'number', example: 90000),
                        new OA\Property(property: 'count', type: 'integer', example: 3),
                        new OA\Property(property: 'by_purpose', type: 'object', example: ['platform_subscription' => ['count' => 1, 'total' => 20000]]),
                    ]),
                    new OA\Property(property: 'previous_revenue', type: 'object', properties: [
                        new OA\Property(property: 'total', type: 'number', example: 40000),
                        new OA\Property(property: 'count', type: 'integer', example: 2),
                    ]),
                    new OA\Property(property: 'series', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'date', type: 'string', format: 'date'),
                        new OA\Property(property: 'total', type: 'number'),
                        new OA\Property(property: 'count', type: 'integer'),
                    ], type: 'object')),
                    new OA\Property(property: 'range', type: 'object'),
                    new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function billingSummary(): void {}

    #[OA\Get(
        path: '/admin/billing/clients',
        operationId: 'adminBillingClients',
        tags: ['Platform Admin'],
        summary: 'Client companies with subscription lifecycle and billing',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'subscription_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'active', 'past_due', 'expired', 'suspended', 'cancelled'])),
            new OA\Parameter(name: 'expiring_within', in: 'query', required: false, description: 'Only clients whose period ends within N days', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['period_ends', 'name', 'created'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Clients', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Clients retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'subdomain', type: 'string', nullable: true),
                        new OA\Property(property: 'subscription_status', type: 'string', example: 'active'),
                        new OA\Property(property: 'subscription_period_ends_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'days_remaining', type: 'integer', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean'),
                        new OA\Property(property: 'expiring_soon', type: 'boolean'),
                        new OA\Property(property: 'mrr', type: 'number', example: 20000),
                        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
                        new OA\Property(property: 'payments_count', type: 'integer', example: 3),
                        new OA\Property(property: 'total_paid', type: 'number', example: 60000),
                        new OA\Property(property: 'last_payment_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'last_payment_amount', type: 'number', nullable: true),
                    ], type: 'object')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function billingClients(): void {}

    #[OA\Get(
        path: '/admin/billing/clients/{company}',
        operationId: 'adminBillingClient',
        tags: ['Platform Admin'],
        summary: 'One client with subscription, usage and billing history',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Client', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Client retrieved'),
                new OA\Property(property: 'data', type: 'object', description: 'Client row + subscription, usage and payments'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function billingClient(): void {}

    #[OA\Get(
        path: '/admin/billing/renewals',
        operationId: 'adminBillingRenewals',
        tags: ['Platform Admin'],
        summary: 'Clients whose subscription is due within N days (and overdue)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'days', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 7))],
        responses: [
            new OA\Response(response: 200, description: 'Renewals', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Renewals retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'days', type: 'integer', example: 7),
                    new OA\Property(property: 'count', type: 'integer', example: 2),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function billingRenewals(): void {}

    #[OA\Get(
        path: '/admin/billing/payments',
        operationId: 'adminBillingPayments',
        tags: ['Platform Admin'],
        summary: 'Platform revenue payments (subscriptions, renewals, installation, hardware)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'purpose', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['platform_subscription', 'subscription_renewal', 'installation_request', 'device_purchase', 'marketplace_order'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'paid', 'failed', 'cancelled', 'expired'])),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Platform payments', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Platform payments retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'reference', type: 'string'),
                        new OA\Property(property: 'purpose', type: 'string'),
                        new OA\Property(property: 'company_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'company_name', type: 'string', nullable: true),
                        new OA\Property(property: 'amount', type: 'number'),
                        new OA\Property(property: 'currency', type: 'string'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
                    ], type: 'object')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function billingPayments(): void {}
}
