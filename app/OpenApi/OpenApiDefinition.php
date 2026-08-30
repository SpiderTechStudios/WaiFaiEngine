<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'WaiFai Engine API',
    version: '1.0.0',
    description: 'Hotspot management API: authentication, current-company operations, staff, and platform administration.'
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
#[OA\Tag(name: 'Signup', description: 'Pay-first public signup (intent → payment → complete)')]
#[OA\Tag(name: 'Dashboard', description: 'Home KPIs')]
#[OA\Tag(name: 'Income', description: 'Revenue by source')]
#[OA\Tag(name: 'Routers', description: 'Hotspot routers')]
#[OA\Tag(name: 'Packages', description: 'Internet packages')]
#[OA\Tag(name: 'Vouchers', description: 'Prepaid voucher codes')]
#[OA\Tag(name: 'Payments', description: 'Customer payments')]
#[OA\Tag(name: 'Sessions', description: 'Hotspot sessions — list, inspect, and write after captive portal success')]
#[OA\Tag(name: 'Customers', description: 'Hotspot customers')]
#[OA\Tag(name: 'Staff', description: 'Company staff')]
#[OA\Tag(name: 'Branches', description: 'Business locations')]
#[OA\Tag(name: 'Withdrawals', description: 'Payouts from company wallet')]
#[OA\Tag(name: 'Settings', description: 'Company branding and integrations')]
#[OA\Tag(name: 'Portal', description: 'Public captive portal (no authentication)')]
#[OA\Tag(name: 'Superadmin', description: 'Platform administration')]
#[OA\Tag(name: 'Platform Admin', description: 'Cross-tenant router management for platform admins')]
class OpenApiDefinition {}
