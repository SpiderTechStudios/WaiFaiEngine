<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class WiFiDogDocumentation
{
    #[OA\Get(
        path: '/wifidog/login',
        operationId: 'wifiDogLogin',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog login — AP redirects unauthenticated client to portal',
        description: 'Public gateway protocol (no Sanctum). Resolves gw_id/gw_sn to a registered Ruijie router, creates/reuses a pending captive session, then 302-redirects the browser to the production /connect portal. NEVER returns Auth:1 and NEVER redirects to the AP /wifidog/auth URL.',
        parameters: [
            new OA\Parameter(name: 'gw_id', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '58b4bb192d35'), description: 'AP device id / MAC'),
            new OA\Parameter(name: 'gw_sn', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'G1UQ5C8006474'), description: 'AP serial number'),
            new OA\Parameter(name: 'gw_address', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: '192.168.0.144'), description: 'AP LAN IP for later browser→AP auth redirect'),
            new OA\Parameter(name: 'gw_port', in: 'query', required: true, schema: new OA\Schema(type: 'integer', example: 2060)),
            new OA\Parameter(name: 'url', in: 'query', required: false, schema: new OA\Schema(type: 'string', description: 'Original URL the client tried to open')),
            new OA\Parameter(name: 'ip', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '192.168.0.93')),
            new OA\Parameter(name: 'mac', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '9E:5D:2F:AF:FC:88')),
            new OA\Parameter(name: 'apmac', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ssid', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'vlanid', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to production connect portal'),
            new OA\Response(response: 400, description: 'Missing gw_id', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Unknown or inactive gateway', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(): void {}

    #[OA\Get(
        path: '/wifidog/auth',
        operationId: 'wifiDogAuth',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog auth — AP server-to-server token verification',
        description: 'Called by the RAP62-OD after the browser hits http://{gw_address}:{gw_port}/wifidog/auth?token=.... Returns exactly Auth:1 or Auth:0 as plain text (HTTP 200). Not JSON. Stages: login, logout, counter/counters, query.',
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'stage', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['login', 'logout', 'counter', 'counters', 'query'], example: 'login')),
            new OA\Parameter(name: 'gw_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'gw_sn', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ip', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'mac', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'incoming', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'outgoing', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'vlanid', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plain text Auth:1 or Auth:0', content: new OA\MediaType(mediaType: 'text/plain', schema: new OA\Schema(type: 'string', example: 'Auth:1'))),
        ]
    )]
    public function auth(): void {}

    #[OA\Get(
        path: '/wifidog/portal',
        operationId: 'wifiDogPortal',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog portal — post-auth result page',
        description: 'Browser landing after AP verifies the token. Success redirects to the original URL; message=denied shows a failure page. Never returns Auth:1.',
        parameters: [
            new OA\Parameter(name: 'gw_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'gw_sn', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'mac', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'token', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'message', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'denied')),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Redirect to original URL or connect portal'),
            new OA\Response(response: 200, description: 'HTML failure page when message=denied'),
        ]
    )]
    public function portal(): void {}

    #[OA\Get(
        path: '/wifidog/ping',
        operationId: 'wifiDogPing',
        tags: ['WiFiDog Gateway Protocol'],
        summary: 'WiFiDog ping — AP heartbeat',
        description: 'Public gateway protocol. Updates gateway last_seen_at and returns plain text Pong.',
        parameters: [
            new OA\Parameter(name: 'gw_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'gw_sn', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
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
