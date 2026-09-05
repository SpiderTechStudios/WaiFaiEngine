<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class CaptiveDocumentation
{
    #[OA\Get(
        path: '/captive/sessions/{token}',
        operationId: 'captiveSessionShow',
        tags: ['Captive Session'],
        summary: 'Resolve a captive portal session by token',
        description: 'Public frontend API. Returns safe network/gateway/client context for the connect portal. Network is bound to the session created by WiFiDog login — do not trust subdomain from the URL alone for authorization.',
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string', minLength: 64, maxLength: 64)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Captive session', content: new OA\JsonContent(ref: '#/components/schemas/CaptiveSessionResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/captive/sessions/{token}/authorize',
        operationId: 'captiveSessionAuthorize',
        tags: ['Captive Session'],
        summary: 'Authorize a captive session after voucher or payment',
        description: 'Marks the captive session authenticated, creates a hotspot NetworkSession, and returns gateway_auth_url for the client to hit the gateway WiFiDog auth endpoint.',
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string', minLength: 64, maxLength: 64)),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'access_grant_id', type: 'integer', nullable: true),
            new OA\Property(property: 'payment_transaction_id', type: 'integer', nullable: true),
            new OA\Property(property: 'mac_address', type: 'string', nullable: true, example: 'AA:BB:CC:DD:EE:FF'),
            new OA\Property(property: 'ip_address', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Authorized', content: new OA\JsonContent(ref: '#/components/schemas/CaptiveSessionResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function authorize(): void {}
}
