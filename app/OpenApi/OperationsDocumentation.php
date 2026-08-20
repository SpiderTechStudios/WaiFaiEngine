<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class OperationsDocumentation
{
    #[OA\Get(path: '/dashboard', operationId: 'dashboard', tags: ['Dashboard'], summary: 'Manager dashboard KPIs', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Dashboard')])]
    public function dashboard(): void {}

    #[OA\Get(path: '/income', operationId: 'income', tags: ['Income'], summary: 'Income by source and last 14 days', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Income')])]
    public function income(): void {}

    #[OA\Get(path: '/device-setup', operationId: 'deviceSetup', tags: ['Device Setup'], summary: 'MikroTik and Ruijie Cloud setup instructions', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Instructions')])]
    public function deviceSetup(): void {}

    #[OA\Get(path: '/routers', operationId: 'listRouters', tags: ['Routers'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Routers')])]
    public function listRouters(): void {}

    #[OA\Post(
        path: '/routers',
        operationId: 'createRouter',
        tags: ['Routers'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Main AP'),
            new OA\Property(property: 'vendor', type: 'string', example: 'mikrotik'),
            new OA\Property(property: 'model', type: 'string', nullable: true),
            new OA\Property(property: 'serial_number', type: 'string', nullable: true),
            new OA\Property(property: 'mac_address', type: 'string', nullable: true),
            new OA\Property(property: 'ip_address', type: 'string', nullable: true),
            new OA\Property(property: 'branch_id', type: 'integer', nullable: true),
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createRouter(): void {}

    #[OA\Get(path: '/routers/{router}', operationId: 'showRouter', tags: ['Routers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Router')])]
    public function showRouter(): void {}

    #[OA\Patch(path: '/routers/{router}', operationId: 'updateRouter', tags: ['Routers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function updateRouter(): void {}

    #[OA\Delete(path: '/routers/{router}', operationId: 'deleteRouter', tags: ['Routers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted')])]
    public function deleteRouter(): void {}

    #[OA\Get(path: '/packages', operationId: 'listPackages', tags: ['Packages'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Packages')])]
    public function listPackages(): void {}

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
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createPackage(): void {}

    #[OA\Get(path: '/packages/{package}', operationId: 'showPackage', tags: ['Packages'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Package')])]
    public function showPackage(): void {}

    #[OA\Patch(path: '/packages/{package}', operationId: 'updatePackage', tags: ['Packages'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function updatePackage(): void {}

    #[OA\Delete(path: '/packages/{package}', operationId: 'deletePackage', tags: ['Packages'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'package', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted')])]
    public function deletePackage(): void {}

    #[OA\Get(path: '/vouchers', operationId: 'listVouchers', tags: ['Vouchers'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Vouchers')])]
    public function listVouchers(): void {}

    #[OA\Post(
        path: '/vouchers',
        operationId: 'createVouchers',
        tags: ['Vouchers'],
        summary: 'Generate voucher codes for a package',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['internet_plan_id', 'quantity'], properties: [
            new OA\Property(property: 'internet_plan_id', type: 'integer'),
            new OA\Property(property: 'quantity', type: 'integer', example: 10),
            new OA\Property(property: 'name', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createVouchers(): void {}

    #[OA\Get(path: '/payments', operationId: 'listPayments', tags: ['Payments'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Payments')])]
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
        responses: [new OA\Response(response: 201, description: 'Recorded')]
    )]
    public function createPayment(): void {}

    #[OA\Get(path: '/sessions', operationId: 'listSessions', tags: ['Sessions'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Sessions')])]
    public function listSessions(): void {}

    #[OA\Get(path: '/customers', operationId: 'listCustomers', tags: ['Customers'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Customers')])]
    public function listCustomers(): void {}

    #[OA\Get(path: '/branches', operationId: 'listBranches', tags: ['Branches'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Branches')])]
    public function listBranches(): void {}

    #[OA\Post(path: '/branches', operationId: 'createBranch', tags: ['Branches'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'address', type: 'string', nullable: true)])), responses: [new OA\Response(response: 201, description: 'Created')])]
    public function createBranch(): void {}

    #[OA\Get(path: '/withdrawals', operationId: 'listWithdrawals', tags: ['Withdrawals'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Withdrawals and wallet balance')])]
    public function listWithdrawals(): void {}

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
        responses: [new OA\Response(response: 201, description: 'Requested')]
    )]
    public function createWithdrawal(): void {}

    #[OA\Get(path: '/settings', operationId: 'showSettings', tags: ['Settings'], summary: 'Current company settings', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Settings')])]
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
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateSettings(): void {}
}
