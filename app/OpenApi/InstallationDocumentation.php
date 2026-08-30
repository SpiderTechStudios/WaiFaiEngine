<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class InstallationDocumentation
{
    #[OA\Post(
        path: '/installation-requests',
        operationId: 'createInstallationRequest',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Create installation / router supply request (server-priced)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['service_type', 'quantity'], properties: [
            new OA\Property(property: 'service_type', type: 'string', enum: ['installation_only', 'router_and_installation']),
            new OA\Property(property: 'quantity', type: 'integer', example: 2),
            new OA\Property(property: 'customer_notes', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 403, description: 'Forbidden or inactive subscription'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(): void {}

    #[OA\Get(
        path: '/installation-requests',
        operationId: 'listInstallationRequests',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'List')]
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/installation-requests/{installationRequest}',
        operationId: 'showInstallationRequest',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'installationRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Details with progress')]
    )]
    public function show(): void {}

    #[OA\Get(
        path: '/admin/installation-requests',
        operationId: 'adminListInstallationRequests',
        tags: ['Installation Requests'],
        security: [['sanctum' => []]],
        summary: 'Platform admin list',
        responses: [new OA\Response(response: 200, description: 'List')]
    )]
    public function adminIndex(): void {}
}
