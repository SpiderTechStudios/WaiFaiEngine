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

    #[OA\Post(
        path: '/device-setup',
        operationId: 'storeDeviceSetup',
        tags: ['Device Setup'],
        summary: 'Save device setup credentials (Ruijie / portal subdomain)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'portal_subdomain', type: 'string', nullable: true),
            new OA\Property(property: 'ruijie_account_id', type: 'string', nullable: true),
            new OA\Property(property: 'ruijie_password', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 201, description: 'Saved')]
    )]
    public function storeDeviceSetup(): void {}

    #[OA\Patch(
        path: '/device-setup',
        operationId: 'updateDeviceSetup',
        tags: ['Device Setup'],
        summary: 'Update device setup credentials',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'portal_subdomain', type: 'string', nullable: true),
            new OA\Property(property: 'ruijie_account_id', type: 'string', nullable: true),
            new OA\Property(property: 'ruijie_password', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateDeviceSetup(): void {}

    #[OA\Get(path: '/routers', operationId: 'listRouters', tags: ['Routers'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Routers')])]
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
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function createRouter(): void {}

    #[OA\Get(path: '/routers/{router}', operationId: 'showRouter', tags: ['Routers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Router')])]
    public function showRouter(): void {}

    #[OA\Patch(path: '/routers/{router}', operationId: 'updateRouter', tags: ['Routers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function updateRouter(): void {}

    #[OA\Delete(path: '/routers/{router}', operationId: 'deleteRouter', tags: ['Routers'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted')])]
    public function deleteRouter(): void {}

    #[OA\Post(
        path: '/routers/{router}/sync',
        operationId: 'syncRouter',
        tags: ['Routers'],
        summary: 'Sync a Ruijie router from Ruijie Cloud',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Synced'),
            new OA\Response(response: 422, description: 'Not a Ruijie router or credentials missing'),
            new OA\Response(response: 502, description: 'Ruijie Cloud sync failed'),
        ]
    )]
    public function syncRouter(): void {}

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
        responses: [new OA\Response(response: 200, description: 'Vouchers')]
    )]
    public function listVouchers(): void {}

    #[OA\Post(
        path: '/vouchers',
        operationId: 'createVouchers',
        tags: ['Vouchers'],
        summary: 'Generate voucher codes for a router and package',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['router_id', 'package_id', 'quantity'], properties: [
            new OA\Property(property: 'router_id', type: 'integer', example: 1),
            new OA\Property(property: 'package_id', type: 'integer', example: 1),
            new OA\Property(property: 'quantity', type: 'integer', example: 10, description: 'Number of codes to generate'),
            new OA\Property(property: 'custom_code', type: 'string', nullable: true, example: '123456', description: 'Numbers only. Allowed only when quantity is 1. Leave empty to auto-generate.'),
            new OA\Property(property: 'max_uses', type: 'integer', nullable: true, example: 1, description: 'Max uses per code. Defaults to 1.'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'note', type: 'string', nullable: true),
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
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
            new OA\Response(response: 200, description: 'Revoked'),
            new OA\Response(response: 422, description: 'Already revoked or expired'),
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
            new OA\Response(response: 200, description: 'Consumed'),
            new OA\Response(response: 422, description: 'Voucher not usable'),
        ]
    )]
    public function consumeVoucher(): void {}

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

    #[OA\Get(
        path: '/payments/{payment}',
        operationId: 'showPayment',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Payment')]
    )]
    public function showPayment(): void {}

    #[OA\Get(path: '/sessions', operationId: 'listSessions', tags: ['Sessions'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Sessions')])]
    public function listSessions(): void {}

    #[OA\Get(
        path: '/sessions/{session}',
        operationId: 'showSession',
        tags: ['Sessions'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Session')]
    )]
    public function showSession(): void {}

    #[OA\Get(path: '/customers', operationId: 'listCustomers', tags: ['Customers'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Customers')])]
    public function listCustomers(): void {}

    #[OA\Get(
        path: '/customers/{customer}',
        operationId: 'showCustomer',
        tags: ['Customers'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'customer', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Customer')]
    )]
    public function showCustomer(): void {}

    #[OA\Get(path: '/branches', operationId: 'listBranches', tags: ['Branches'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Branches')])]
    public function listBranches(): void {}

    #[OA\Post(path: '/branches', operationId: 'createBranch', tags: ['Branches'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'address', type: 'string', nullable: true)])), responses: [new OA\Response(response: 201, description: 'Created')])]
    public function createBranch(): void {}

    #[OA\Get(
        path: '/branches/{branch}',
        operationId: 'showBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Branch')]
    )]
    public function showBranch(): void {}

    #[OA\Patch(
        path: '/branches/{branch}',
        operationId: 'updateBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateBranch(): void {}

    #[OA\Delete(
        path: '/branches/{branch}',
        operationId: 'deleteBranch',
        tags: ['Branches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function deleteBranch(): void {}

    #[OA\Get(path: '/withdrawals', operationId: 'listWithdrawals', tags: ['Withdrawals'], security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Withdrawals and wallet balance')])]
    public function listWithdrawals(): void {}

    #[OA\Get(
        path: '/withdrawals/stats',
        operationId: 'withdrawalStats',
        tags: ['Withdrawals'],
        summary: 'Wallet and withdrawal totals by status',
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'Stats')]
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
        responses: [new OA\Response(response: 201, description: 'Requested')]
    )]
    public function createWithdrawal(): void {}

    #[OA\Get(
        path: '/withdrawals/{withdrawal}',
        operationId: 'showWithdrawal',
        tags: ['Withdrawals'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Withdrawal')]
    )]
    public function showWithdrawal(): void {}

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
