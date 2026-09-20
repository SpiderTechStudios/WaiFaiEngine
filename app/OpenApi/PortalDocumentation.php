<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class PortalDocumentation
{
    #[OA\Get(
        path: '/portal/{subdomain}',
        operationId: 'portalBootstrap',
        tags: ['Portal'],
        summary: 'Captive portal bootstrap (branding and packages)',
        parameters: [
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'demo-cafe')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Bootstrap', content: new OA\JsonContent(ref: '#/components/schemas/PortalBootstrapResponse')),
            new OA\Response(response: 404, description: 'Portal not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function bootstrap(): void {}

    #[OA\Post(
        path: '/portal/{subdomain}/payments',
        operationId: 'portalCreatePayment',
        tags: ['Portal'],
        summary: 'Initiate captive-portal payment (USSD push via default provider)',
        description: 'Creates a pending company payment and immediately initiates collection on the platform default payment provider (e.g. PalmPesa USSD). Poll GET /portal/{subdomain}/payments/{payment} until status=paid, then open gateway_auth_url (or POST /sessions with captive_session).',
        parameters: [
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['internet_plan_id', 'customer_phone'], properties: [
            new OA\Property(property: 'internet_plan_id', type: 'integer', example: 1),
            new OA\Property(property: 'customer_name', type: 'string', nullable: true, example: 'Jane Guest'),
            new OA\Property(property: 'customer_phone', type: 'string', example: '0712345678'),
            new OA\Property(property: 'customer_email', type: 'string', format: 'email', nullable: true),
            new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'mobile_money'),
            new OA\Property(property: 'captive_session', type: 'string', nullable: true, description: '32-char token from WiFiDog login redirect (?session=)'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Payment initiated (USSD pushed)', content: new OA\JsonContent(ref: '#/components/schemas/PortalPaymentCreatedResponse')),
            new OA\Response(response: 404, description: 'Portal not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createPayment(): void {}

    #[OA\Get(
        path: '/portal/{subdomain}/payments/{payment}',
        operationId: 'portalShowPayment',
        tags: ['Portal'],
        summary: 'Poll payment status after USSD / mobile-money checkout',
        description: 'Poll until status is paid or failed. When paid and captive_session was supplied at create, gateway_auth_url is returned so the browser can complete Ruijie/WiFiDog auth.',
        parameters: [
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/PortalPaymentResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function showPayment(): void {}

    #[OA\Post(
        path: '/portal/{subdomain}/vouchers/redeem',
        operationId: 'portalRedeemVoucher',
        tags: ['Portal'],
        summary: 'Redeem a voucher code from the captive portal',
        parameters: [
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['code', 'customer_name', 'customer_phone'], properties: [
            new OA\Property(property: 'code', type: 'string', example: '123456'),
            new OA\Property(property: 'customer_name', type: 'string', example: 'Jane Guest'),
            new OA\Property(property: 'customer_phone', type: 'string', example: '0712345678'),
            new OA\Property(property: 'customer_email', type: 'string', format: 'email', nullable: true),
            new OA\Property(property: 'captive_session', type: 'string', nullable: true, description: '32-char token from WiFiDog login'),
            new OA\Property(property: 'mac_address', type: 'string', nullable: true, example: 'AA:BB:CC:DD:EE:FF'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Redeemed', content: new OA\JsonContent(ref: '#/components/schemas/PortalVoucherRedeemResponse')),
            new OA\Response(response: 404, description: 'Portal not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function redeemVoucher(): void {}

    #[OA\Post(
        path: '/portal/{subdomain}/sessions',
        operationId: 'portalCreateSession',
        tags: ['Portal'],
        summary: 'Start a hotspot session after payment or voucher grant',
        description: 'After payment is paid, call this with payment_transaction_id + captive_session (and mac if needed). Response includes captive.gateway_auth_url — open it in the browser to finish WiFiDog/Ruijie auth so the client gets internet.',
        parameters: [
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['mac_address'], properties: [
            new OA\Property(property: 'payment_transaction_id', type: 'integer', nullable: true),
            new OA\Property(property: 'access_grant_id', type: 'integer', nullable: true),
            new OA\Property(property: 'mac_address', type: 'string', example: 'AA:BB:CC:DD:EE:FF'),
            new OA\Property(property: 'ip_address', type: 'string', format: 'ipv4', nullable: true),
            new OA\Property(property: 'router_id', type: 'integer', nullable: true),
            new OA\Property(property: 'session_id', type: 'string', nullable: true),
            new OA\Property(property: 'captive_session', type: 'string', nullable: true, description: '32-char token from WiFiDog login redirect'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Session started', content: new OA\JsonContent(ref: '#/components/schemas/SessionCreatedResponse')),
            new OA\Response(response: 404, description: 'Portal not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function createSession(): void {}

    #[OA\Post(
        path: '/portal/{subdomain}/restore',
        operationId: 'portalRestoreAccess',
        tags: ['Portal'],
        summary: 'Restore active access by customer phone',
        parameters: [
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['customer_phone'], properties: [
            new OA\Property(property: 'customer_phone', type: 'string', example: '0712345678'),
            new OA\Property(property: 'mac_address', type: 'string', nullable: true, example: 'AA:BB:CC:DD:EE:FF'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Access restored', content: new OA\JsonContent(ref: '#/components/schemas/PortalRestoreResponse')),
            new OA\Response(response: 404, description: 'Portal not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function restore(): void {}
}
