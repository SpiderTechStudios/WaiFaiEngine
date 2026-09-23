<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class SuperAdminDocumentation
{
    #[OA\Get(
        path: '/superadmin/users',
        operationId: 'superadminListUsers',
        tags: ['Superadmin'],
        summary: 'List all users',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15))],
        responses: [
            new OA\Response(response: 200, description: 'Users', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedUsersResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function usersIndex(): void {}

    #[OA\Get(
        path: '/superadmin/users/{user}',
        operationId: 'superadminShowUser',
        tags: ['Superadmin'],
        summary: 'View a user',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function usersShow(): void {}

    #[OA\Patch(
        path: '/superadmin/users/{user}/suspend',
        operationId: 'superadminSuspendUser',
        tags: ['Superadmin'],
        summary: 'Suspend a user account',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Suspended', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Cannot suspend self', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function usersSuspend(): void {}

    #[OA\Patch(
        path: '/superadmin/users/{user}/activate',
        operationId: 'superadminActivateUser',
        tags: ['Superadmin'],
        summary: 'Activate a user account',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Activated', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function usersActivate(): void {}

    #[OA\Get(
        path: '/superadmin/companies',
        operationId: 'superadminListCompanies',
        tags: ['Superadmin'],
        summary: 'List all companies',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15))],
        responses: [
            new OA\Response(response: 200, description: 'Companies', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedCompaniesResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function companiesIndex(): void {}

    #[OA\Get(
        path: '/superadmin/companies/{company}',
        operationId: 'superadminShowCompany',
        tags: ['Superadmin'],
        summary: 'View any company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Company', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function companiesShow(): void {}

    #[OA\Patch(
        path: '/superadmin/companies/{company}/suspend',
        operationId: 'superadminSuspendCompany',
        tags: ['Superadmin'],
        summary: 'Suspend a company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Suspended', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function companiesSuspend(): void {}

    #[OA\Patch(
        path: '/superadmin/companies/{company}/activate',
        operationId: 'superadminActivateCompany',
        tags: ['Superadmin'],
        summary: 'Activate a company',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Activated', content: new OA\JsonContent(ref: '#/components/schemas/CompanyResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function companiesActivate(): void {}

    #[OA\Get(
        path: '/superadmin/payments',
        operationId: 'superadminListPayments',
        tags: ['Superadmin'],
        summary: 'List payments across all companies',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments', content: new OA\JsonContent(ref: '#/components/schemas/PaymentListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function paymentsIndex(): void {}

    #[OA\Get(
        path: '/superadmin/payments/{payment}',
        operationId: 'superadminShowPayment',
        tags: ['Superadmin'],
        summary: 'View any payment',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'payment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Payment', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function paymentsShow(): void {}

    #[OA\Get(
        path: '/superadmin/withdrawals',
        operationId: 'superadminListWithdrawals',
        tags: ['Superadmin'],
        summary: 'List withdrawals across all companies',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Withdrawals', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function withdrawalsIndex(): void {}

    #[OA\Get(
        path: '/superadmin/withdrawals/{withdrawal}',
        operationId: 'superadminShowWithdrawal',
        tags: ['Superadmin'],
        summary: 'View any withdrawal',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'withdrawal', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Withdrawal', content: new OA\JsonContent(ref: '#/components/schemas/WithdrawalResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function withdrawalsShow(): void {}

    #[OA\Get(
        path: '/superadmin/enrollments',
        operationId: 'superadminListEnrollments',
        tags: ['Superadmin'],
        summary: 'List enrollments stuck before payment',
        description: 'Enrollments that registered but have not completed payment (pending_payment, payment_failed, processing_payment, expired, cancelled), with their latest provider payment status and attempt history. Includes a status summary.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending_payment', 'payment_failed', 'processing_payment', 'expired', 'cancelled'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Matches reference, email, phone, payment phone, or business name'),
            new OA\Parameter(name: 'expired_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Only expired enrollments'),
            new OA\Parameter(name: 'active_only', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), description: 'Only active reservations (pending/failed/processing)'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Stuck enrollments', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedEnrollmentsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function enrollmentsIndex(): void {}

    #[OA\Get(
        path: '/superadmin/enrollments/{enrollment}',
        operationId: 'superadminShowEnrollment',
        tags: ['Superadmin'],
        summary: 'View a stuck enrollment and its payment history',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'enrollment', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'ENR-ABC123XYZ'), description: 'Enrollment reference'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Enrollment', content: new OA\JsonContent(ref: '#/components/schemas/EnrollmentAdminResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function enrollmentsShow(): void {}
}

#[OA\Schema(
    schema: 'PaginatedUsersResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean'),
        new OA\Property(property: 'code', type: 'integer'),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ],
            type: 'object'
        ),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedCompaniesResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean'),
        new OA\Property(property: 'code', type: 'integer'),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Company')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ],
            type: 'object'
        ),
    ]
)]
class SuperAdminSchemas {}
