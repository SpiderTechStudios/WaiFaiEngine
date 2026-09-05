<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class WiFiDogDocumentation
{
    #[OA\Get(
        path: '/wifidog/login',
        operationId: 'wifiDogLogin',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog login — validate gateway and redirect to captive portal',
        description: 'Public gateway protocol endpoint (no Sanctum). Resolves gw_id to a registered Ruijie/WiFiDog router, creates or reuses a captive session, then 302-redirects the client browser to the frontend /connect portal. Do not send client_ip — use ip. Never trust subdomain/company_id from the client; network is derived from the gateway.',
        parameters: [
            new OA\Parameter(name: 'gw_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'G1UQCC8000976'), description: 'WiFiDog gateway id registered on the router'),
            new OA\Parameter(name: 'ip', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '192.168.0.35')),
            new OA\Parameter(name: 'mac', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'AA:BB:CC:DD:EE:FF')),
            new OA\Parameter(name: 'gw_address', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '192.168.0.1')),
            new OA\Parameter(name: 'gw_port', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2060)),
            new OA\Parameter(name: 'ssid', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'url', in: 'query', required: false, schema: new OA\Schema(type: 'string', description: 'Original URL the client tried to open')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to /connect?subdomain=...&session=... or gateway auth if already authenticated'),
            new OA\Response(response: 400, description: 'Missing gw_id', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Unknown or inactive gateway', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(): void {}

    #[OA\Get(
        path: '/wifidog/auth',
        operationId: 'wifiDogAuth',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog auth — authorize or deny a client token',
        description: 'Public gateway protocol. Returns plain text Auth: 1 or Auth: 0 (not JSON).',
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'stage', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['login', 'counters', 'logout'], example: 'login')),
            new OA\Parameter(name: 'ip', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'mac', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'gw_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'incoming', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'outgoing', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plain text Auth: 1 or Auth: 0', content: new OA\MediaType(mediaType: 'text/plain', schema: new OA\Schema(type: 'string', example: 'Auth: 1'))),
        ]
    )]
    public function auth(): void {}

    #[OA\Get(
        path: '/wifidog/ping',
        operationId: 'wifiDogPing',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog ping — gateway heartbeat',
        description: 'Public gateway protocol. Updates gateway last_seen_at and returns plain text Pong.',
        parameters: [
            new OA\Parameter(name: 'gw_id', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sys_uptime', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sys_memfree', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sys_load', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'wifidog_uptime', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plain text Pong', content: new OA\MediaType(mediaType: 'text/plain', schema: new OA\Schema(type: 'string', example: 'Pong'))),
            new OA\Response(response: 404, description: 'Unknown or inactive gateway', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function ping(): void {}
}
