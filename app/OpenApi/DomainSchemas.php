<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Router',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'gateway_type', type: 'string', enum: ['mikrotik', 'ruijie', 'wavlink'], example: 'mikrotik'),
        new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot'),
        new OA\Property(property: 'lan_ip', type: 'string', nullable: true, example: '192.168.88.1'),
        new OA\Property(property: 'api_host', type: 'string', nullable: true, example: '41.59.12.34'),
        new OA\Property(property: 'api_port', type: 'integer', nullable: true, example: 443),
        new OA\Property(property: 'api_username', type: 'string', nullable: true, example: 'admin'),
        new OA\Property(property: 'api_password_set', type: 'boolean', example: true),
        new OA\Property(property: 'gateway_id', type: 'string', nullable: true, example: 'G1UQCC8000976'),
        new OA\Property(property: 'serial_number', type: 'string', nullable: true),
        new OA\Property(property: 'wifidog_port', type: 'integer', nullable: true, example: 2060),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'online', type: 'boolean', example: true),
        new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'branch', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Main Branch'),
            new OA\Property(property: 'station_id', type: 'integer', example: 1),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'AdminRouter',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/Router'),
        new OA\Schema(properties: [
            new OA\Property(property: 'company', ref: '#/components/schemas/Company'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'Package',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: '1 Hour'),
        new OA\Property(property: 'badge', type: 'string', nullable: true, example: 'Popular'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Fast hourly access'),
        new OA\Property(property: 'duration', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'duration_unit', type: 'string', enum: ['HOURS', 'DAYS', 'WEEKS', 'MONTHS', 'UNLIMITED_DATA'], example: 'HOURS'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 1000),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Voucher',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: '123456'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'expired', 'revoked'], example: 'active'),
        new OA\Property(property: 'max_uses', type: 'integer', example: 1),
        new OA\Property(property: 'uses_count', type: 'integer', example: 0),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Front desk pack'),
        new OA\Property(property: 'revoked_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'router', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot'),
            new OA\Property(property: 'gateway_type', type: 'string', example: 'mikrotik'),
        ]),
        new OA\Property(property: 'package', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: '1 Hour'),
            new OA\Property(property: 'price', type: 'number', example: 1000),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Customer',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Walk in'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0700555666'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Payment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reference', type: 'string', example: 'PAY-ABC123XYZ'),
        new OA\Property(property: 'amount', type: 'number', example: 1000),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'mpesa'),
        new OA\Property(property: 'status', type: 'string', example: 'paid'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'customer', ref: '#/components/schemas/Customer'),
        new OA\Property(property: 'package', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: '1 Hour'),
        ]),
        new OA\Property(property: 'company', ref: '#/components/schemas/Company', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Withdrawal',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reference', type: 'string', example: 'WD-ABC123XYZ0'),
        new OA\Property(property: 'provider', type: 'string', example: 'mpesa'),
        new OA\Property(property: 'destination_phone', type: 'string', example: '0700111222'),
        new OA\Property(property: 'destination_name', type: 'string', nullable: true, example: 'Jane Doe'),
        new OA\Property(property: 'amount', type: 'number', example: 400),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'requested_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
        new OA\Property(property: 'company', ref: '#/components/schemas/Company', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Branch',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Main Branch'),
        new OA\Property(property: 'code', type: 'string', nullable: true, example: 'main-branch'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Dar es Salaam'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'HotspotSession',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'mac_address', type: 'string', nullable: true, example: 'AA:BB:CC:DD:EE:FF'),
        new OA\Property(property: 'ip_address', type: 'string', nullable: true, example: '192.168.88.50'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ended_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_activity_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'upload_bytes', type: 'integer', nullable: true, example: 1024),
        new OA\Property(property: 'download_bytes', type: 'integer', nullable: true, example: 2048),
        new OA\Property(property: 'customer', ref: '#/components/schemas/Customer', nullable: true),
        new OA\Property(property: 'router', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'MikroTik-Hotspot'),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'DashboardData',
    properties: [
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'today_revenue', type: 'number', example: 15000),
        new OA\Property(property: 'today_payments', type: 'integer', example: 12),
        new OA\Property(property: 'total_revenue', type: 'number', example: 250000),
        new OA\Property(property: 'active_sessions', type: 'integer', example: 8),
        new OA\Property(property: 'routers_online', type: 'integer', example: 3),
        new OA\Property(property: 'routers_total', type: 'integer', example: 4),
        new OA\Property(
            property: 'recent_sessions',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'mac_address', type: 'string', example: 'AA:BB:CC:DD:EE:FF'),
                new OA\Property(property: 'status', type: 'string', example: 'active'),
                new OA\Property(property: 'description', type: 'string', example: 'Walk in - ABC Internet'),
            ], type: 'object')
        ),
    ]
)]
#[OA\Schema(
    schema: 'IncomeData',
    properties: [
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'by_source', type: 'object', properties: [
            new OA\Property(property: 'mobile_money', type: 'number', example: 80000),
            new OA\Property(property: 'voucher', type: 'number', example: 20000),
        ]),
        new OA\Property(
            property: 'last_14_days',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'date', type: 'string', example: '2026-08-20'),
                new OA\Property(property: 'total', type: 'number', example: 5000),
            ], type: 'object')
        ),
        new OA\Property(property: 'total', type: 'number', example: 100000),
    ]
)]
#[OA\Schema(
    schema: 'DeviceSetupData',
    properties: [
        new OA\Property(property: 'portal_url', type: 'string', nullable: true, example: 'https://api.example.com/connect?subdomain=abc-internet'),
        new OA\Property(property: 'subdomain', type: 'string', nullable: true, example: 'abc-internet'),
        new OA\Property(
            property: 'methods',
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'key', type: 'string', example: 'mikrotik'),
                new OA\Property(property: 'name', type: 'string', example: 'MikroTik hotspot'),
                new OA\Property(property: 'steps', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'portal_url', type: 'string', nullable: true),
                new OA\Property(property: 'ruijie_account_configured', type: 'boolean', nullable: true),
            ], type: 'object')
        ),
        new OA\Property(property: 'ruijie_account_id', type: 'string', nullable: true, example: 'ruijie-user'),
        new OA\Property(property: 'ruijie_password_set', type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'SettingsData',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'ABC Internet'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Africa/Dar_es_Salaam'),
        new OA\Property(property: 'subdomain', type: 'string', nullable: true, example: 'abc-internet'),
        new OA\Property(property: 'portal_url', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'primary_color', type: 'string', nullable: true, example: '#0F4C81'),
        new OA\Property(property: 'logo_url', type: 'string', nullable: true),
        new OA\Property(property: 'voucher_code_digits', type: 'integer', example: 6),
        new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'both'),
        new OA\Property(property: 'captive_portal_welcome_message', type: 'string', nullable: true),
        new OA\Property(property: 'ruijie_account_id', type: 'string', nullable: true),
        new OA\Property(property: 'ruijie_password_set', type: 'boolean', example: false),
    ]
)]
#[OA\Schema(
    schema: 'WithdrawalStatsData',
    properties: [
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'wallet_balance', type: 'number', example: 600),
        new OA\Property(property: 'pending_count', type: 'integer', example: 1),
        new OA\Property(property: 'pending_amount', type: 'number', example: 400),
        new OA\Property(property: 'completed_count', type: 'integer', example: 5),
        new OA\Property(property: 'completed_amount', type: 'number', example: 20000),
        new OA\Property(property: 'failed_count', type: 'integer', example: 0),
        new OA\Property(property: 'failed_amount', type: 'number', example: 0),
        new OA\Property(property: 'total_count', type: 'integer', example: 6),
        new OA\Property(property: 'total_amount', type: 'number', example: 20400),
    ]
)]
#[OA\Schema(
    schema: 'VoucherConsumeData',
    properties: [
        new OA\Property(property: 'voucher', ref: '#/components/schemas/Voucher'),
        new OA\Property(property: 'customer', ref: '#/components/schemas/Customer'),
        new OA\Property(property: 'access_grant', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'status', type: 'string', example: 'active'),
            new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'source', type: 'string', example: 'voucher'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'VouchersCreatedData',
    properties: [
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Voucher')),
    ]
)]
#[OA\Schema(
    schema: 'AdminRouterDetailData',
    properties: [
        new OA\Property(property: 'router', ref: '#/components/schemas/AdminRouter'),
        new OA\Property(property: 'company', ref: '#/components/schemas/Company'),
    ]
)]
class DomainSchemas {}
