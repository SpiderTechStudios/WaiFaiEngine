<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class SuperAdminDocumentation
{
    #[OA\Get(
        path: '/superadmin/users',
        operationId: 'superadminListUsers',
        tags: ['Superadmin'],
        summary: 'List all users',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15))],
        responses: [
            new OA\Response(response: 200, description: 'Users', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedUsersResponse')),
            new OA\Response(response: 403, description: 'Not a superadmin', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function usersIndex(): void {}

    #[OA\Get(
        path: '/superadmin/users/{user}',
        operationId: 'superadminShowUser',
        tags: ['Superadmin'],
        summary: 'View a user',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
        ]
    )]
    public function usersShow(): void {}

    #[OA\Patch(
        path: '/superadmin/users/{user}/suspend',
        operationId: 'superadminSuspendUser',
        tags: ['Superadmin'],
        summary: 'Suspend a user account',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Suspended', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 422, description: 'Cannot suspend self', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function usersSuspend(): void {}

    #[OA\Patch(
        path: '/superadmin/users/{user}/activate',
        operationId: 'superadminActivateUser',
        tags: ['Superadmin'],
        summary: 'Activate a user account',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Activated', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
        ]
    )]
    public function usersActivate(): void {}

    #[OA\Get(
        path: '/superadmin/companies',
        operationId: 'superadminListCompanies',
        tags: ['Superadmin'],
        summary: 'List all companies',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15))],
        responses: [
            new OA\Response(response: 200, description: 'Companies', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedCompaniesResponse')),
        ]
    )]
    public function companiesIndex(): void {}

    #[OA\Get(
        path: '/superadmin/companies/{company}',
        operationId: 'superadminShowCompany',
        tags: ['Superadmin'],
        summary: 'View any company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Company', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
        ]
    )]
    public function companiesShow(): void {}

    #[OA\Patch(
        path: '/superadmin/companies/{company}/suspend',
        operationId: 'superadminSuspendCompany',
        tags: ['Superadmin'],
        summary: 'Suspend a company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Suspended', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
        ]
    )]
    public function companiesSuspend(): void {}

    #[OA\Patch(
        path: '/superadmin/companies/{company}/activate',
        operationId: 'superadminActivateCompany',
        tags: ['Superadmin'],
        summary: 'Activate a company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Activated', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
        ]
    )]
    public function companiesActivate(): void {}
}

#[OA\Schema(
    schema: 'PaginatedUsersResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean'),
        new OA\Property(property: 'code', type: 'integer'),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ],
            type: 'object'
        ),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedCompaniesResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean'),
        new OA\Property(property: 'code', type: 'integer'),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Company')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ],
            type: 'object'
        ),
    ]
)]
class SuperAdminSchemas {}
