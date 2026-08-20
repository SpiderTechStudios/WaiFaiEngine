<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: false),
        new OA\Property(property: 'code', type: 'integer', example: 422),
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'data', type: 'object', nullable: true, additionalProperties: true),
    ]
)]
#[OA\Schema(
    schema: 'MessageResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Success'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'), example: []),
    ]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe', description: 'Computed from first_name and last_name'),
        new OA\Property(property: 'first_name', type: 'string', example: 'Jane'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0700123456'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'suspended', 'inactive']),
        new OA\Property(property: 'is_superadmin', type: 'boolean', example: false),
        new OA\Property(property: 'is_admin', type: 'boolean', example: false),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'current_company_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Company',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'subdomain', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', example: 'UTC'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Role',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string', enum: ['owner', 'manager', 'operator', 'cashier']),
        new OA\Property(property: 'is_system', type: 'boolean'),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]
#[OA\Schema(
    schema: 'Membership',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'suspended', 'removed']),
        new OA\Property(property: 'joined_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'company', ref: '#/components/schemas/Company'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'role', ref: '#/components/schemas/Role'),
    ]
)]
#[OA\Schema(
    schema: 'AuthSessionData',
    description: 'Session payload for /auth/me and /auth/company/switch. Token is omitted.',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'companies', type: 'array', items: new OA\Items(ref: '#/components/schemas/Membership')),
        new OA\Property(property: 'current_company', ref: '#/components/schemas/Company', nullable: true),
        new OA\Property(property: 'membership', ref: '#/components/schemas/Membership', nullable: true),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
    ]
)]
#[OA\Schema(
    schema: 'AuthSessionTokenData',
    description: 'Session payload for /auth/login. Includes a Sanctum token.',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/AuthSessionData'),
        new OA\Schema(required: ['token'], properties: [
            new OA\Property(property: 'token', type: 'string', example: '1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'AuthSessionResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Authenticated user'),
        new OA\Property(property: 'data', ref: '#/components/schemas/AuthSessionData'),
    ]
)]
#[OA\Schema(
    schema: 'AuthSessionTokenResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Logged in successfully'),
        new OA\Property(property: 'data', ref: '#/components/schemas/AuthSessionTokenData'),
    ]
)]
#[OA\Schema(
    schema: 'AuthSessionCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Registered successfully'),
        new OA\Property(property: 'data', ref: '#/components/schemas/AuthSessionTokenData'),
    ]
)]
#[OA\Schema(
    schema: 'UserCreatedResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 201),
        new OA\Property(property: 'message', type: 'string', example: 'Administrator registered successfully'),
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'CompanyResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Company'),
    ]
)]
#[OA\Schema(
    schema: 'CompanyListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Company')),
    ]
)]
#[OA\Schema(
    schema: 'MembershipResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Membership'),
    ]
)]
#[OA\Schema(
    schema: 'UserResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ]
)]
class Schemas {}
