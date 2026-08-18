<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class StaffDocumentation
{
    #[OA\Get(
        path: '/staff',
        operationId: 'listStaff',
        tags: ['Staff'],
        summary: 'List staff for the current company',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Staff list'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/staff',
        operationId: 'addStaff',
        tags: ['Staff'],
        summary: 'Add or invite staff',
        security: [['sanctum' => []]],
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
            new OA\Response(response: 201, description: 'Staff added'),
        ]
    )]
    public function store(): void {}

    #[OA\Get(path: '/staff/{membership}', operationId: 'showStaff', tags: ['Staff'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Membership')])]
    public function show(): void {}

    #[OA\Patch(path: '/staff/{membership}', operationId: 'updateStaffRoleAlias', tags: ['Staff'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['role'], properties: [new OA\Property(property: 'role', type: 'string', enum: ['manager', 'operator', 'cashier'])])), responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function updateRoleAlias(): void {}

    #[OA\Patch(path: '/staff/{membership}/role', operationId: 'updateStaffRole', tags: ['Staff'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['role'], properties: [new OA\Property(property: 'role', type: 'string', enum: ['manager', 'operator', 'cashier'])])), responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function updateRole(): void {}

    #[OA\Patch(path: '/staff/{membership}/suspend', operationId: 'suspendStaff', tags: ['Staff'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Suspended')])]
    public function suspend(): void {}

    #[OA\Patch(path: '/staff/{membership}/activate', operationId: 'activateStaff', tags: ['Staff'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Activated')])]
    public function activate(): void {}

    #[OA\Delete(path: '/staff/{membership}', operationId: 'removeStaff', tags: ['Staff'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'membership', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Removed')])]
    public function destroy(): void {}

    #[OA\Post(
        path: '/ownership/transfer',
        operationId: 'transferOwnership',
        tags: ['Staff'],
        summary: 'Transfer company ownership',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['membership_id'], properties: [new OA\Property(property: 'membership_id', type: 'integer')])),
        responses: [new OA\Response(response: 200, description: 'Transferred')]
    )]
    public function transferOwnership(): void {}
}
