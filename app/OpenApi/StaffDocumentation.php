<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class StaffDocumentation
{
    #[OA\Get(
        path: '/companies/{company}/staff',
        operationId: 'listStaff',
        tags: ['Staff'],
        summary: 'List company staff',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Staff list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'boolean'),
                        new OA\Property(property: 'code', type: 'integer'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Membership')),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/companies/{company}/staff',
        operationId: 'addStaff',
        tags: ['Staff'],
        summary: 'Add or invite staff',
        description: 'If the email already exists, a membership is created. Otherwise a pending user is invited.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role', type: 'string', enum: ['manager', 'operator', 'cashier']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Staff added', content: new OA\JsonContent(ref: '#/components/schemas/MembershipResponse')),
            new OA\Response(response: 422, description: 'Duplicate membership or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/companies/{company}/staff/{membership}',
        operationId: 'showStaff',
        tags: ['Staff'],
        summary: 'View a staff membership',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Membership', content: new OA\JsonContent(ref: '#/components/schemas/MembershipResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/companies/{company}/staff/{membership}',
        operationId: 'updateStaffRoleAlias',
        tags: ['Staff'],
        summary: 'Update staff role (alias)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [new OA\Property(property: 'role', type: 'string', enum: ['manager', 'operator', 'cashier'])]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated', content: new OA\JsonContent(ref: '#/components/schemas/MembershipResponse')),
            new OA\Response(response: 403, description: 'Owner protection or missing permission', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updateRoleAlias(): void {}

    #[OA\Patch(
        path: '/companies/{company}/staff/{membership}/role',
        operationId: 'updateStaffRole',
        tags: ['Staff'],
        summary: 'Update staff role',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [new OA\Property(property: 'role', type: 'string', enum: ['manager', 'operator', 'cashier'])]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated', content: new OA\JsonContent(ref: '#/components/schemas/MembershipResponse')),
        ]
    )]
    public function updateRole(): void {}

    #[OA\Patch(
        path: '/companies/{company}/staff/{membership}/suspend',
        operationId: 'suspendStaff',
        tags: ['Staff'],
        summary: 'Suspend a company membership',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Suspended', content: new OA\JsonContent(ref: '#/components/schemas/MembershipResponse')),
        ]
    )]
    public function suspend(): void {}

    #[OA\Patch(
        path: '/companies/{company}/staff/{membership}/activate',
        operationId: 'activateStaff',
        tags: ['Staff'],
        summary: 'Activate a company membership',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Activated', content: new OA\JsonContent(ref: '#/components/schemas/MembershipResponse')),
        ]
    )]
    public function activate(): void {}

    #[OA\Delete(
        path: '/companies/{company}/staff/{membership}',
        operationId: 'removeStaff',
        tags: ['Staff'],
        summary: 'Remove staff from the company',
        description: 'Marks the membership as removed. Does not delete the global user.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Removed', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ]
    )]
    public function destroy(): void {}

    #[OA\Post(
        path: '/companies/{company}/ownership/transfer',
        operationId: 'transferOwnership',
        tags: ['Staff'],
        summary: 'Transfer company ownership',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['membership_id'],
                properties: [new OA\Property(property: 'membership_id', type: 'integer')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ownership transferred', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function transferOwnership(): void {}
}
