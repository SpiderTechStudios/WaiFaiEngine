<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class ExpenseDocumentation
{
    #[OA\Get(
        path: '/expenses',
        operationId: 'listExpenses',
        tags: ['Expenses'],
        summary: 'List expenses for the current company',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'router_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Use router_id=null or scope=business for business-wide expenses'),
            new OA\Parameter(name: 'scope', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['business', 'router'])),
            new OA\Parameter(name: 'expense_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'platform', 'operational'])),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'paid_to', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'currency', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['paid_at', 'amount', 'created_at', 'id'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Expenses', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedExpensesResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/expenses/summary',
        operationId: 'expenseSummary',
        tags: ['Expenses'],
        summary: 'Totals for the current company (platform vs operational)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'router_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'expense_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'platform', 'operational'])),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Expense summary', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseSummaryResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function summary(): void {}

    #[OA\Post(
        path: '/expenses',
        operationId: 'createExpense',
        tags: ['Expenses'],
        summary: 'Record an expense',
        description: 'Category and source are derived from the expense type; router_id is optional (business-wide when omitted).',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreExpenseRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Expense recorded', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/expenses/{expense}',
        operationId: 'showExpense',
        tags: ['Expenses'],
        summary: 'View an expense',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expense', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Expense', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function show(): void {}

    #[OA\Patch(
        path: '/expenses/{expense}',
        operationId: 'updateExpense',
        tags: ['Expenses'],
        summary: 'Update an expense',
        description: 'System-generated expenses cannot be edited.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expense', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreExpenseRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Expense updated', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/expenses/{expense}',
        operationId: 'deleteExpense',
        tags: ['Expenses'],
        summary: 'Soft delete an expense',
        description: 'Financial records are soft deleted to preserve history.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expense', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Expense deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function destroy(): void {}

    #[OA\Get(
        path: '/expense-types',
        operationId: 'listActiveExpenseTypes',
        tags: ['Expenses'],
        summary: 'List active expense types users can select',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Expense types', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseTypeListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function expenseTypes(): void {}

    #[OA\Get(
        path: '/routers/{router}/expenses',
        operationId: 'routerExpenses',
        tags: ['Expenses'],
        summary: 'Expense report for a router',
        description: 'Returns the router, totals, a per-expense-type breakdown, and the paginated expenses.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'router', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all', 'platform', 'operational'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Router expense report', content: new OA\JsonContent(ref: '#/components/schemas/RouterExpenseReportResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function routerExpenses(): void {}

    #[OA\Get(
        path: '/admin/expense-types',
        operationId: 'adminListExpenseTypes',
        tags: ['Expense Types'],
        summary: 'List expense types (platform admin)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['platform', 'operational'])),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Expense types', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedExpenseTypesResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function adminIndex(): void {}

    #[OA\Post(
        path: '/admin/expense-types',
        operationId: 'adminCreateExpenseType',
        tags: ['Expense Types'],
        summary: 'Create an expense type (platform admin)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreExpenseTypeRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Expense type created', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseTypeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function adminStore(): void {}

    #[OA\Get(
        path: '/admin/expense-types/{expenseType}',
        operationId: 'adminShowExpenseType',
        tags: ['Expense Types'],
        summary: 'View an expense type (platform admin)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expenseType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Expense type', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseTypeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function adminShow(): void {}

    #[OA\Patch(
        path: '/admin/expense-types/{expenseType}',
        operationId: 'adminUpdateExpenseType',
        tags: ['Expense Types'],
        summary: 'Update an expense type (platform admin)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expenseType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreExpenseTypeRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Expense type updated', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseTypeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function adminUpdate(): void {}

    #[OA\Delete(
        path: '/admin/expense-types/{expenseType}',
        operationId: 'adminDeleteExpenseType',
        tags: ['Expense Types'],
        summary: 'Delete an expense type (platform admin)',
        description: 'Types used by existing expenses must be deactivated instead of deleted.',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expenseType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Expense type deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
            new OA\Response(response: 422, description: 'Expense type is in use', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function adminDestroy(): void {}

    #[OA\Post(
        path: '/admin/expense-types/{expenseType}/activate',
        operationId: 'adminActivateExpenseType',
        tags: ['Expense Types'],
        summary: 'Activate an expense type (platform admin)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expenseType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Expense type activated', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseTypeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function adminActivate(): void {}

    #[OA\Post(
        path: '/admin/expense-types/{expenseType}/deactivate',
        operationId: 'adminDeactivateExpenseType',
        tags: ['Expense Types'],
        summary: 'Deactivate an expense type (platform admin)',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'expenseType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Expense type deactivated', content: new OA\JsonContent(ref: '#/components/schemas/ExpenseTypeResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ]
    )]
    public function adminDeactivate(): void {}
}

#[OA\Schema(
    schema: 'ExpenseType',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Internet'),
        new OA\Property(property: 'slug', type: 'string', example: 'internet'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'category', type: 'string', enum: ['platform', 'operational'], example: 'operational'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Expense',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'description', type: 'string', example: 'Monthly internet bundle for Router #12'),
        new OA\Property(property: 'amount', type: 'string', example: '50000.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'TZS'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date', example: '2026-09-25'),
        new OA\Property(property: 'paid_to', type: 'string', example: 'Airtel'),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'AIR-09252026'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'recorded'),
        new OA\Property(property: 'source', type: 'string', enum: ['manual', 'system'], example: 'manual'),
        new OA\Property(property: 'category', type: 'string', enum: ['platform', 'operational'], example: 'operational'),
        new OA\Property(property: 'router', type: 'object', nullable: true, description: 'Null for business-wide expenses'),
        new OA\Property(property: 'expense_type', ref: '#/components/schemas/ExpenseType', nullable: true),
        new OA\Property(property: 'created_by', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'StoreExpenseRequest',
    required: ['expense_type_id', 'description', 'amount', 'paid_at', 'paid_to'],
    properties: [
        new OA\Property(property: 'router_id', type: 'integer', nullable: true, example: 12, description: 'Omitted/null = business-wide'),
        new OA\Property(property: 'expense_type_id', type: 'integer', example: 2),
        new OA\Property(property: 'description', type: 'string', example: 'Monthly internet bundle for Router #12'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50000),
        new OA\Property(property: 'currency', type: 'string', nullable: true, example: 'TZS'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date', example: '2026-09-25'),
        new OA\Property(property: 'paid_to', type: 'string', example: 'Airtel'),
        new OA\Property(property: 'reference', type: 'string', nullable: true, example: 'AIR-09252026'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'StoreExpenseTypeRequest',
    required: ['name', 'category'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Electricity'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'category', type: 'string', enum: ['platform', 'operational'], example: 'operational'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
#[OA\Schema(
    schema: 'ExpenseResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Expense'),
    ]
)]
#[OA\Schema(
    schema: 'ExpenseTypeResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', ref: '#/components/schemas/ExpenseType'),
    ]
)]
#[OA\Schema(
    schema: 'ExpenseTypeListResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ExpenseType')),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedExpensesResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Expenses retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Expense')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedExpenseTypesResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: 'Expense types retrieved'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/ExpenseType')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'ExpenseSummaryResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'total', type: 'number', nullable: true, example: 350000),
            new OA\Property(property: 'currency', type: 'string', nullable: true, example: 'TZS'),
            new OA\Property(property: 'platform_total', type: 'number', nullable: true, example: 49999),
            new OA\Property(property: 'operational_total', type: 'number', nullable: true, example: 300001),
            new OA\Property(property: 'count', type: 'integer', example: 12),
            new OA\Property(property: 'by_currency', type: 'array', items: new OA\Items(type: 'object'), description: 'Per-currency breakdown when multiple currencies are present'),
        ], type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'RouterExpenseReportResponse',
    properties: [
        new OA\Property(property: 'status', type: 'boolean', example: true),
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'router', type: 'object', properties: [
                new OA\Property(property: 'id', type: 'integer', example: 12),
                new OA\Property(property: 'name', type: 'string', example: 'Mbezi Router'),
            ]),
            new OA\Property(property: 'summary', type: 'object'),
            new OA\Property(property: 'breakdown', type: 'array', items: new OA\Items(type: 'object')),
            new OA\Property(property: 'expenses', type: 'array', items: new OA\Items(ref: '#/components/schemas/Expense')),
            new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
        ], type: 'object'),
    ]
)]
class ExpenseSchemas {}
