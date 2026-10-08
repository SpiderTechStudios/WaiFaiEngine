<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class OperationsDocumentation
{
    #[OA\Get(
        path: '/dashboard',
        operationId: 'dashboard',
        tags: ['Dashboard'],
        summary: 'Manager dashboard KPIs',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard', content: new OA\JsonContent(ref: '#/components/schemas/DashboardResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function dashboard(): void {}

    #[OA\Get(
        path: '/income',
        operationId: 'income',
        tags: ['Income'],
        summary: 'Income report for a date range',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, description: 'Y-m-d, default today − 13 days', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, description: 'Y-m-d, default today, not in the future, max range 366 days', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'compare', in: 'query', required: false, description: '1 = include previous period of same length', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'branch_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'router_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Income', content: new OA\JsonContent(ref: '#/components/schemas/IncomeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Invalid range or filter', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function income(): void {}

    #[OA\Get(
        path: '/income/export',
        operationId: 'incomeExport',
        tags: ['Income'],
        summary: 'Download the income report as CSV or PDF',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'format', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['csv', 'pdf'])),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'branch_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'router_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File download (text/csv or application/pdf)'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Invalid range, filter or format', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function incomeExport(): void {}

    #[OA\Get(
        path: '/routers',
        operationId: 'listRouters',
        tags: ['Routers'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Routers', content: new OA\JsonContent(ref: '#/components/schemas/RouterListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listRouters(): void {}

    #[OA\Post(
        path: '/routers',
        operationId: 'createRouter',
        tags: ['Routers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['gateway_type', 'name'], properties: [
            new OA\Property(property: 'gateway_type', type: 'string', enum: ['mikrotik', 'ruijie', 'wavlink'], example: 'mikrotik'),
            new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot', description: 'MikroTik identity / gateway name / Wavlink router label'),
            new OA\Property(property: 'lan_ip', type: 'string', nullable: true, example: '192.168.88.1', description: 'Required for mikrotik and ruijie. Optional for wavlink.'),
            new OA\Property(property: 'api_host', type: 'string', nullable: true, example: '41.59.12.34', description: 'MikroTik only. Public IP or DDNS. Never a private IP.'),
            new OA\Property(property: 'api_port', type: 'integer', nullable: true, example: 443, description: 'MikroTik only. Defaults to 443.'),
            new OA\Property(property: 'api_username', type: 'string', nullable: true, description: 'MikroTik only.'),
            new OA\Property(property: 'api_password', type: 'string', nullable: true, description: 'MikroTik only. Stored encrypted; never returned.'),
            new OA\Property(property: 'gateway_id', type: 'string', nullable: true, example: 'G1UQCC8000976', description: 'Ruijie only. WiFiDog gw_id.'),
            new OA\Property(property: 'serial_number', type: 'string', nullable: true, description: 'Ruijie only. Optional.'),
            new OA\Property(property: 'wifidog_port', type: 'integer', nullable: true, example: 2060, description: 'Ruijie only. Defaults to 2060.'),
            new OA\Property(property: 'branch_id', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/RouterCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createRouter(): void {}

    #[OA\Get(
        path: '/routers/{router}',
        operationId: 'showRouter',
        tags: ['Routers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Router', content: new OA\JsonContent(ref: '#/components/schemas/RouterResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showRouter(): void {}

    #[OA\Patch(
        path: '/routers/{router}',
        operationId: 'updateRouter',
        tags: ['Routers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'gateway_type', type: 'string', enum: ['mikrotik', 'ruijie', 'wavlink'], example: 'mikrotik'),
            new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot', description: 'MikroTik identity / gateway name / Wavlink router label'),
            new OA\Property(property: 'lan_ip', type: 'string', nullable: true, example: '192.168.88.1', description: 'Required for mikrotik and ruijie. Optional for wavlink.'),
            new OA\Property(property: 'api_host', type: 'string', nullable: true, example: '41.59.12.34', description: 'MikroTik only. Public IP or DDNS. Never a private IP.'),
            new OA\Property(property: 'api_port', type: 'integer', nullable: true, example: 443, description: 'MikroTik only. Defaults to 443.'),
            new OA\Property(property: 'api_username', type: 'string', nullable: true, description: 'MikroTik only.'),
            new OA\Property(property: 'api_password', type: 'string', nullable: true, description: 'MikroTik only. Stored encrypted; never returned.'),
            new OA\Property(property: 'gateway_id', type: 'string', nullable: true, example: 'G1UQCC8000976', description: 'Ruijie only. WiFiDog gw_id.'),
            new OA\Property(property: 'serial_number', type: 'string', nullable: true, description: 'Ruijie only. Optional.'),
            new OA\Property(property: 'wifidog_port', type: 'integer', nullable: true, example: 2060, description: 'Ruijie only. Defaults to 2060.'),
            new OA\Property(property: 'branch_id', type: 'integer', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'offline'], nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/RouterResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function updateRouter(): void {}

    #[OA\Delete(
        path: '/routers/{router}',
        operationId: 'deleteRouter',
        tags: ['Routers'],
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
        path: '/routers/{router}/sync',
        operationId: 'syncRouter',
        tags: ['Routers'],
        summary: 'Sync a Ruijie router from Ruijie Cloud',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Synced', content: new OA\JsonContent(ref: '#/components/schemas/RouterResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Not a Ruijie router or credentials missing', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 502, description: 'Ruijie Cloud sync failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function syncRouter(): void {}

    #[OA\Get(
        path: '/routers/summary',
        operationId: 'routerSummary',
        tags: ['Routers'],
        summary: 'Header totals for the routers page (all routers, ignores list filters)',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Router summary retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'total', type: 'integer', example: 4),
                    new OA\Property(property: 'online', type: 'integer', example: 3),
                    new OA\Property(property: 'offline', type: 'integer', example: 1),
                    new OA\Property(property: 'clients_now', type: 'integer', example: 12),
                    new OA\Property(property: 'revenue_today', type: 'number', example: 32000),
                    new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
                    new OA\Property(property: 'branches', type: 'integer', example: 2),
                    new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function routerSummary(): void {}

    #[OA\Get(
        path: '/routers/{router}/events',
        operationId: 'routerEvents',
        tags: ['Routers'],
        summary: 'Router activity: ongoing offline event plus the audit trail',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Events', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Router events retrieved'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'offline', description: 'offline | created | updated | synced | rebooted | deleted'),
                    new OA\Property(property: 'at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'duration_seconds', type: 'integer', nullable: true),
                    new OA\Property(property: 'ongoing', type: 'boolean', example: true),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'by', type: 'string', nullable: true),
                ], type: 'object')),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function routerEvents(): void {}

    #[OA\Post(
        path: '/routers/{router}/test',
        operationId: 'testRouter',
        tags: ['Routers'],
        summary: 'Test the connection to a router (TCP reachability or Ruijie Cloud)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Test result', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Router connection tested'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'gateway_type', type: 'string', example: 'mikrotik'),
                    new OA\Property(property: 'reachable', type: 'boolean', example: true),
                    new OA\Property(property: 'latency_ms', type: 'integer', example: 42),
                    new OA\Property(property: 'host', type: 'string', nullable: true, example: '192.168.88.1'),
                    new OA\Property(property: 'port', type: 'integer', nullable: true, example: 443),
                    new OA\Property(property: 'message', type: 'string', example: 'Router address is reachable.'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Missing address or Ruijie Cloud credentials', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function testRouter(): void {}

    #[OA\Post(
        path: '/routers/{router}/reboot',
        operationId: 'rebootRouter',
        tags: ['Routers'],
        summary: 'Reboot a MikroTik router through the RouterOS REST API',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Reboot accepted', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Reboot requested'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'requested', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Reboot command accepted by the router.'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Unsupported gateway or missing API credentials', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 502, description: 'Router API unreachable or rejected the command', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function rebootRouter(): void {}

    #[OA\Get(
        path: '/routers/{router}/setup',
        operationId: 'routerSetup',
        tags: ['Routers'],
        summary: 'Connection guide for this router (portal URL, API host, commands)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Setup guide', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Router setup retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'mikrotik'),
                    new OA\Property(property: 'portal_url', type: 'string', example: 'https://api.waifai.co.tz/connect?subdomain=abc'),
                    new OA\Property(property: 'server_host', type: 'string', example: 'api.waifai.co.tz'),
                    new OA\Property(property: 'steps', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'commands', type: 'array', items: new OA\Items(type: 'string')),
                    ], type: 'object')),
                    new OA\Property(property: 'commands', type: 'array', items: new OA\Items(type: 'string')),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function routerSetup(): void {}

    #[OA\Get(
        path: '/packages',
        operationId: 'listPackages',
        tags: ['Packages'],
        summary: 'List packages ordered for the portal, with all-time sales metrics',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'active', 'inactive', 'draft'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Matches name, badge and description'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Packages', content: new OA\JsonContent(ref: '#/components/schemas/PackageListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listPackages(): void {}

    #[OA\Get(
        path: '/packages/summary',
        operationId: 'packageSummary',
        tags: ['Packages'],
        summary: 'Package header totals and period sales',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['today', '7d', '30d', '90d', 'all'], default: '30d')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: true),
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Package summary retrieved'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'period', type: 'string', example: '30d'),
                    new OA\Property(property: 'range', type: 'object', properties: [
                        new OA\Property(property: 'from', type: 'string', nullable: true, example: '2026-09-09'),
                        new OA\Property(property: 'to', type: 'string', nullable: true, example: '2026-10-08'),
                        new OA\Property(property: 'timezone', type: 'string', example: 'Africa/Dar_es_Salaam'),
                    ]),
                    new OA\Property(property: 'total', type: 'integer', example: 4),
                    new OA\Property(property: 'active', type: 'integer', example: 3),
                    new OA\Property(property: 'inactive', type: 'integer', example: 1),
                    new OA\Property(property: 'draft', type: 'integer', example: 0),
                    new OA\Property(property: 'average_price', type: 'integer', nullable: true, example: 1500),
                    new OA\Property(property: 'lowest_price', type: 'integer', nullable: true, example: 500),
                    new OA\Property(property: 'highest_price', type: 'integer', nullable: true, example: 5000),
                    new OA\Property(property: 'best_seller', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 3),
                        new OA\Property(property: 'name', type: 'string', example: 'Daily'),
                        new OA\Property(property: 'sold', type: 'integer', example: 42),
                        new OA\Property(property: 'revenue', type: 'number', example: 42000),
                    ]),
                    new OA\Property(property: 'revenue', type: 'number', example: 182000),
                    new OA\Property(property: 'previous_revenue', type: 'number', example: 150000),
                    new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
                    new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function packageSummary(): void {}

    #[OA\Put(
        path: '/packages/order',
        operationId: 'reorderPackages',
        tags: ['Packages'],
        summary: 'Set the portal order for packages',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['ids'], properties: [
            new OA\Property(property: 'ids', type: 'array', example: [3, 1, 2], items: new OA\Items(type: 'integer')),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Order updated (returns the full ordered list)', content: new OA\JsonContent(ref: '#/components/schemas/PackageListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Invalid ids or packages from another company', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function reorderPackages(): void {}

    #[OA\Post(
        path: '/packages',
        operationId: 'createPackage',
        tags: ['Packages'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'price', 'duration_unit'], properties: [
            new OA\Property(property: 'name', type: 'string', example: '1 Hour'),
            new OA\Property(property: 'price', type: 'number', example: 1000),
            new OA\Property(property: 'duration', type: 'integer', nullable: true, example: 1, description: 'Required unless duration_unit is UNLIMITED_DATA'),
            new OA\Property(property: 'duration_unit', type: 'string', enum: ['HOURS', 'DAYS', 'WEEKS', 'MONTHS', 'UNLIMITED_DATA'], example: 'HOURS'),
            new OA\Property(property: 'badge', type: 'string', nullable: true, example: 'Popular'),
            new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Fast hourly access'),
            new OA\Property(property: 'status', type: 'string', nullable: true, enum: ['active', 'inactive', 'draft'], example: 'active'),
            new OA\Property(property: 'speed_download_mbps', type: 'integer', nullable: true, example: 10),
            new OA\Property(property: 'speed_upload_mbps', type: 'integer', nullable: true, example: 5),
            new OA\Property(property: 'data_cap_mb', type: 'integer', nullable: true, example: 2048, description: 'Null = unlimited'),
            new OA\Property(property: 'devices_allowed', type: 'integer', nullable: true, example: 2, description: 'Null = unlimited'),
            new OA\Property(property: 'visible_on_portal', type: 'boolean', nullable: true, example: true),
            new OA\Property(property: 'sort_order', type: 'integer', nullable: true, example: 1),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/PackageCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createPackage(): void {}

    #[OA\Get(
        path: '/packages/{package}',
        operationId: 'showPackage',
        tags: ['Packages'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Package', content: new OA\JsonContent(ref: '#/components/schemas/PackageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showPackage(): void {}

    #[OA\Patch(
        path: '/packages/{package}',
        operationId: 'updatePackage',
        tags: ['Packages'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', example: '1 Hour'),
            new OA\Property(property: 'price', type: 'number', example: 1000),
            new OA\Property(property: 'duration', type: 'integer', nullable: true, example: 1, description: 'Required unless duration_unit is UNLIMITED_DATA'),
            new OA\Property(property: 'duration_unit', type: 'string', enum: ['HOURS', 'DAYS', 'WEEKS', 'MONTHS', 'UNLIMITED_DATA'], example: 'HOURS'),
            new OA\Property(property: 'badge', type: 'string', nullable: true, example: 'Popular'),
            new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Fast hourly access'),
            new OA\Property(property: 'status', type: 'string', nullable: true, enum: ['active', 'inactive', 'draft'], description: 'Activate / deactivate a package'),
            new OA\Property(property: 'speed_download_mbps', type: 'integer', nullable: true),
            new OA\Property(property: 'speed_upload_mbps', type: 'integer', nullable: true),
            new OA\Property(property: 'data_cap_mb', type: 'integer', nullable: true, description: 'Null = unlimited'),
            new OA\Property(property: 'devices_allowed', type: 'integer', nullable: true, description: 'Null = unlimited'),
            new OA\Property(property: 'visible_on_portal', type: 'boolean', nullable: true),
            new OA\Property(property: 'sort_order', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/PackageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function updatePackage(): void {}

    #[OA\Post(
        path: '/packages/{package}/duplicate',
        operationId: 'duplicatePackage',
        tags: ['Packages'],
        summary: 'Duplicate a package (copy starts inactive and hidden from the portal)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Duplicated', content: new OA\JsonContent(ref: '#/components/schemas/PackageCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function duplicatePackage(): void {}

    #[OA\Delete(
        path: '/packages/{package}',
        operationId: 'deletePackage',
        tags: ['Packages'],
        summary: 'Delete a package. Returns 409 with payment/voucher counts when it has history — deactivate it instead.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 409, description: 'Package has payments/vouchers', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'boolean', example: false),
                new OA\Property(property: 'code', type: 'integer', example: 409),
                new OA\Property(property: 'message', type: 'string', example: 'Package has 12 payments and 8 vouchers. Deactivate it instead.'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'payments', type: 'integer', example: 12),
                    new OA\Property(property: 'vouchers', type: 'integer', example: 8),
                ]),
            ])),
        ]
    )]
    public function deletePackage(): void {}

    #[OA\Get(
        path: '/vouchers',
        operationId: 'listVouchers',
        tags: ['Vouchers'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'expired', 'revoked'])),
            new OA\Parameter(name: 'router_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'package_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Vouchers', content: new OA\JsonContent(ref: '#/components/schemas/VoucherListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listVouchers(): void {}

    #[OA\Post(
        path: '/vouchers',
        operationId: 'createVouchers',
        tags: ['Vouchers'],
        summary: 'Generate voucher codes for a router and package',
        description: 'Router and package must belong to the current company and not be soft-deleted. Missing or deleted refs return 422 (not 404).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['router_id', 'package_id', 'quantity'], properties: [
            new OA\Property(property: 'router_id', type: 'integer', example: 1, description: 'Active company router id. Soft-deleted routers are rejected.'),
            new OA\Property(property: 'package_id', type: 'integer', example: 1, description: 'Active company package id. Soft-deleted packages are rejected.'),
            new OA\Property(property: 'quantity', type: 'integer', example: 10, description: 'Number of codes to generate'),
            new OA\Property(property: 'custom_code', type: 'string', nullable: true, example: '123456', description: 'Numbers only. Allowed only when quantity is 1. Leave empty to auto-generate.'),
            new OA\Property(property: 'max_uses', type: 'integer', nullable: true, example: 1, description: 'Max uses per code. Defaults to 1.'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'note', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/VouchersCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(
                response: 422,
                description: 'Validation error — including missing/deleted router_id or package_id, custom_code rules, or duplicate custom_code',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'),
            ),
        ]
    )]
    public function createVouchers(): void {}

    #[OA\Post(
        path: '/vouchers/{voucher}/revoke',
        operationId: 'revokeVoucher',
        tags: ['Vouchers'],
        summary: 'Revoke an active voucher',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'voucher', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Revoked', content: new OA\JsonContent(ref: '#/components/schemas/VoucherResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Already revoked or expired', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function revokeVoucher(): void {}

    #[OA\Post(
        path: '/vouchers/{voucher}/consume',
        operationId: 'consumeVoucher',
        tags: ['Vouchers'],
        summary: 'Consume a voucher and create an access grant',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'voucher', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'customer_name', type: 'string', nullable: true),
            new OA\Property(property: 'customer_phone', type: 'string', nullable: true),
            new OA\Property(property: 'customer_email', type: 'string', nullable: true),
            new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Consumed', content: new OA\JsonContent(ref: '#/components/schemas/VoucherConsumeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Voucher not usable', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function consumeVoucher(): void {}

    #[OA\Get(
        path: '/payments',
        operationId: 'listPayments',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Payments', content: new OA\JsonContent(ref: '#/components/schemas/PaymentListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listPayments(): void {}

    #[OA\Post(
        path: '/payments',
        operationId: 'createPayment',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['internet_plan_id'], properties: [
            new OA\Property(property: 'internet_plan_id', type: 'integer'),
            new OA\Property(property: 'customer_name', type: 'string'),
            new OA\Property(property: 'customer_phone', type: 'string'),
            new OA\Property(property: 'payment_method', type: 'string', example: 'mpesa'),
            new OA\Property(property: 'amount', type: 'number', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Recorded', content: new OA\JsonContent(ref: '#/components/schemas/PaymentCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createPayment(): void {}

    #[OA\Get(
        path: '/payments/{payment}',
        operationId: 'showPayment',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showPayment(): void {}

    #[OA\Get(
        path: '/sessions',
        operationId: 'listSessions',
        tags: ['Sessions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sessions', content: new OA\JsonContent(ref: '#/components/schemas/SessionListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listSessions(): void {}

    #[OA\Post(
        path: '/sessions',
        operationId: 'createSession',
        tags: ['Sessions'],
        summary: 'Write a hotspot session after payment / captive portal success',
        description: 'Call this when the captive portal has authenticated the device. Pass payment_transaction_id (creates an access grant if needed) or an existing access_grant_id from voucher consume. Soft-deleted or foreign routers return 422.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['mac_address'], properties: [
            new OA\Property(property: 'payment_transaction_id', type: 'integer', nullable: true, example: 1, description: 'Paid payment that unlocks access. Required if access_grant_id is omitted.'),
            new OA\Property(property: 'access_grant_id', type: 'integer', nullable: true, example: 1, description: 'Existing grant (e.g. from voucher consume). Required if payment_transaction_id is omitted.'),
            new OA\Property(property: 'mac_address', type: 'string', example: 'AA:BB:CC:DD:EE:FF'),
            new OA\Property(property: 'ip_address', type: 'string', nullable: true, example: '192.168.88.50'),
            new OA\Property(property: 'router_id', type: 'integer', nullable: true, example: 1, description: 'Active company router id. Soft-deleted routers are rejected with 422.'),
            new OA\Property(property: 'session_id', type: 'string', nullable: true, example: 'hs-abc123', description: 'External hotspot session id from the gateway'),
            new OA\Property(property: 'network_station_id', type: 'integer', nullable: true),
            new OA\Property(property: 'network_ssid_id', type: 'integer', nullable: true),
            new OA\Property(property: 'metadata', type: 'object', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/SessionCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Payment or access grant not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(
                response: 422,
                description: 'Validation error — including missing payment/grant, unpaid payment, expired grant, invalid MAC, or missing/deleted router_id',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'),
            ),
        ]
    )]
    public function createSession(): void {}

    #[OA\Get(
        path: '/sessions/{session}',
        operationId: 'showSession',
        tags: ['Sessions'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Session', content: new OA\JsonContent(ref: '#/components/schemas/SessionResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showSession(): void {}

    #[OA\Get(
        path: '/customers',
        operationId: 'listCustomers',
        tags: ['Customers'],
        summary: 'List hotspot customers with package, time left/used, and lifetime spend',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Search by name, phone, email, or MAC'),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Customers', content: new OA\JsonContent(ref: '#/components/schemas/CustomerListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listCustomers(): void {}

    #[OA\Get(
        path: '/customers/{customer}',
        operationId: 'showCustomer',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Customer', content: new OA\JsonContent(ref: '#/components/schemas/CustomerResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showCustomer(): void {}

    #[OA\Get(
        path: '/branches',
        operationId: 'listBranches',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Branches', content: new OA\JsonContent(ref: '#/components/schemas/BranchListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listBranches(): void {}

    #[OA\Post(
        path: '/branches',
        operationId: 'createBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'address', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/BranchCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createBranch(): void {}

    #[OA\Get(
        path: '/branches/{branch}',
        operationId: 'showBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Branch', content: new OA\JsonContent(ref: '#/components/schemas/BranchResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showBranch(): void {}

    #[OA\Patch(
        path: '/branches/{branch}',
        operationId: 'updateBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'address', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/BranchResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function updateBranch(): void {}

    #[OA\Delete(
        path: '/branches/{branch}',
        operationId: 'deleteBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function deleteBranch(): void {}

    #[OA\Get(
        path: '/withdrawals',
        operationId: 'listWithdrawals',
        tags: ['Withdrawals'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Withdrawals and wallet balance', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function listWithdrawals(): void {}

    #[OA\Get(
        path: '/withdrawals/stats',
        operationId: 'withdrawalStats',
        tags: ['Withdrawals'],
        summary: 'Wallet and withdrawal totals by status',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Stats', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalStatsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function withdrawalStats(): void {}

    #[OA\Post(
        path: '/withdrawals',
        operationId: 'createWithdrawal',
        tags: ['Withdrawals'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['amount', 'provider', 'destination_phone'], properties: [
            new OA\Property(property: 'amount', type: 'number', example: 10000),
            new OA\Property(property: 'provider', type: 'string', example: 'mpesa'),
            new OA\Property(property: 'destination_phone', type: 'string'),
            new OA\Property(property: 'destination_name', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Requested', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createWithdrawal(): void {}

    #[OA\Get(
        path: '/withdrawals/{withdrawal}',
        operationId: 'showWithdrawal',
        tags: ['Withdrawals'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Withdrawal', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showWithdrawal(): void {}

    #[OA\Delete(
        path: '/withdrawals/{withdrawal}',
        operationId: 'cancelWithdrawal',
        tags: ['Withdrawals'],
        summary: 'Cancel a pending withdrawal',
        description: 'Only `pending` withdrawals can be cancelled. The amount is returned to the company wallet and the row is kept with status `cancelled`.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Cancelled', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Withdrawal is not pending (data.withdrawal[0])', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function cancelWithdrawal(): void {}

    #[OA\Get(
        path: '/settings',
        operationId: 'showSettings',
        tags: ['Settings'],
        summary: 'Current company settings',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Settings', content: new OA\JsonContent(ref: '#/components/schemas/SettingsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function showSettings(): void {}

    #[OA\Patch(
        path: '/settings',
        operationId: 'updateSettings',
        tags: ['Settings'],
        summary: 'Update branding, voucher digits, payout methods, portal message, and Ruijie credentials',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'primary_color', type: 'string', example: '#0F4C81'),
            new OA\Property(property: 'logo_url', type: 'string', nullable: true),
            new OA\Property(property: 'voucher_code_digits', type: 'integer', example: 6),
            new OA\Property(property: 'captive_portal_welcome_message', type: 'string'),
            new OA\Property(property: 'ruijie_account_id', type: 'string'),
            new OA\Property(property: 'ruijie_password', type: 'string'),
            new OA\Property(property: 'portal_subdomain', type: 'string'),
            new OA\Property(property: 'payout_methods', type: 'array', items: new OA\Items(type: 'object')),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/SettingsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function updateSettings(): void {}
}
