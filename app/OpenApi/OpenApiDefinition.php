<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'WaiFai Engine API',
    version: '1.0.0',
    description: 'Multi-tenant authentication, company membership, staff, and platform administration APIs.'
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Laravel Sanctum personal access token. Prefix with Bearer.'
)]
#[OA\Tag(name: 'Auth', description: 'Registration, login, session, and password')]
#[OA\Tag(name: 'Companies', description: 'Company creation and membership-scoped company APIs')]
#[OA\Tag(name: 'Staff', description: 'Company staff and ownership')]
#[OA\Tag(name: 'Superadmin', description: 'Platform administration')]
class OpenApiDefinition {}
