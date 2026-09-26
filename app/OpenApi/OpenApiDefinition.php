<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'WaiFai Engine API',
    version: '1.0.0',
    description: 'WaiFai Engine API v1: payment-gated enrollment (4-minute session, max 3 failed attempts), company billing/renewal, optional installation/router orders, captive portal, hotspot operations, staff, and platform administration. Envelope: { status, code, message, data }. Platform ops require an active subscription (except /billing/*). WiFiDog gateway protocol lives at /api/wifidog/* (select API root server in Swagger).'
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]
#[OA\Server(url: '/api', description: 'API root — WiFiDog gateway protocol (/wifidog/*)')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Laravel Sanctum personal access token. Prefix with Bearer.'
)]
#[OA\Tag(name: 'Auth', description: 'Login, session, password, and email verification')]
#[OA\Tag(name: 'Enrollment', description: 'Pay-first registration: enroll → provider payment → poll/retry → account on verified webhook')]
#[OA\Tag(name: 'Payment Providers', description: 'Superadmin-managed collection/payout providers')]
#[OA\Tag(name: 'Payment Webhooks', description: 'Public provider callbacks with signature verification')]
#[OA\Tag(name: 'Billing', description: 'Company subscription status and renewal')]
#[OA\Tag(name: 'Installation Requests', description: 'Post-subscription router installation / supply orders')]
#[OA\Tag(name: 'Dashboard', description: 'Home KPIs')]
#[OA\Tag(name: 'Income', description: 'Revenue by source')]
#[OA\Tag(name: 'Routers', description: 'Hotspot routers')]
#[OA\Tag(name: 'Packages', description: 'Internet packages')]
#[OA\Tag(name: 'Vouchers', description: 'Prepaid voucher codes')]
#[OA\Tag(name: 'Offers', description: 'Owner-configured free WiFi offers for the captive portal')]
#[OA\Tag(name: 'Payments', description: 'Customer payments')]
#[OA\Tag(name: 'Sessions', description: 'Hotspot sessions — list, inspect, and write after captive portal success')]
#[OA\Tag(name: 'Customers', description: 'Hotspot customers')]
#[OA\Tag(name: 'Staff', description: 'Company staff')]
#[OA\Tag(name: 'Branches', description: 'Business locations')]
#[OA\Tag(name: 'Withdrawals', description: 'Payouts from company wallet')]
#[OA\Tag(name: 'Settings', description: 'Company branding and integrations')]
#[OA\Tag(name: 'Portal', description: 'Public captive portal (no authentication)')]
#[OA\Tag(name: 'Captive Session', description: 'Public captive session resolve/authorize for the connect frontend')]
#[OA\Tag(name: 'WiFiDog Gateway Protocol', description: 'Public machine endpoints for Ruijie/WiFiDog gateways (login redirect, auth, ping) — no Sanctum')]
#[OA\Tag(name: 'Superadmin', description: 'Platform administration')]
#[OA\Tag(name: 'Device Catalog', description: 'Superadmin catalog for brands, device categories, and devices')]
#[OA\Tag(name: 'Marketplace', description: 'Company cart and device purchases. Payment purpose is device_purchase, separate from subscription and installation.')]
#[OA\Tag(name: 'Expenses', description: 'Operating expenses: platform and business/operational spend by router, type, and date')]
#[OA\Tag(name: 'Expense Types', description: 'Platform-admin managed expense categories')]
#[OA\Tag(name: 'Platform Admin', description: 'Cross-tenant dashboard and router management for platform admins')]
class OpenApiDefinition {}
