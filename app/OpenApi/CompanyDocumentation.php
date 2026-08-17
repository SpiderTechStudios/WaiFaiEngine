<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class CompanyDocumentation
{
    #[OA\Get(
        path: '/companies',
        operationId: 'listCompanies',
        tags: ['Companies'],
        summary: 'List companies the user belongs to',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Companies', content: new OA\JsonContent(ref: '#/components/schemas/CompanyListResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/companies',
        operationId: 'createCompany',
        tags: ['Companies'],
        summary: 'Create an additional company and become owner',
        description: 'Use this after registration when the authenticated user needs another company. Initial signup already creates the first company via POST /auth/register.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'ABC Internet Services'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'timezone', type: 'string', example: 'UTC', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/companies/{company}',
        operationId: 'showCompany',
        tags: ['Companies'],
        summary: 'View a company the user belongs to',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Company', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
            new OA\Response(response: 403, description: 'Not a member', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/companies/{company}',
        operationId: 'updateCompany',
        tags: ['Companies'],
        summary: 'Update a company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'timezone', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function update(): void {}
}
