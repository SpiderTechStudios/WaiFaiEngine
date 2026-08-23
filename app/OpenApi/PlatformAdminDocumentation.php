<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class PlatformAdminDocumentation
{
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
}
