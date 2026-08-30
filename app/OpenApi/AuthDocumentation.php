<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class AuthDocumentation
{
    #[OA\Get(
        path: '/',
        operationId: 'authDefaultPage',
        tags: ['Auth'],
        summary: 'API root',
        description: 'Named login fallback. Returns a JSON error instead of a web login page.',
        responses: [
            new OA\Response(response: 401, description: 'Insufficient permissions', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function defaultPage(): void {}

    #[OA\Post(
        path: '/auth/register',
        operationId: 'register',
        tags: ['Auth'],
        summary: 'Deprecated free registration (disabled unless PLATFORM_ALLOW_FREE_REGISTER=true)',
        description: 'Public self-service registration is pay-first. Use POST /signup/intents, pay, then POST /signup/intents/{intent}/complete. This endpoint only works when PLATFORM_ALLOW_FREE_REGISTER is enabled.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Registered with company (only when free register enabled)', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionCreatedResponse')),
            new OA\Response(response: 422, description: 'Disabled or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/auth/login',
        operationId: 'login',
        tags: ['Auth'],
        summary: 'Login',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Logged in', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionTokenResponse')),
            new OA\Response(response: 422, description: 'Invalid credentials or inactive account', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/auth/forgot-password',
        operationId: 'forgotPassword',
        tags: ['Auth'],
        summary: 'Request a password reset',
        description: 'Always returns a generic success message to avoid account enumeration.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Generic success', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function forgotPassword(): void {}

    #[OA\Post(
        path: '/auth/reset-password',
        operationId: 'resetPassword',
        tags: ['Auth'],
        summary: 'Reset password with token',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Password reset', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 422, description: 'Invalid or expired token', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function resetPassword(): void {}

    #[OA\Get(
        path: '/auth/email/verify/{id}/{hash}',
        operationId: 'verifyEmail',
        tags: ['Auth'],
        summary: 'Verify email from signed link',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Email verified', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 403, description: 'Invalid verification link', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function verifyEmail(): void {}

    #[OA\Post(
        path: '/auth/admin/register',
        operationId: 'registerAdmin',
        tags: ['Auth'],
        summary: 'Register a platform administrator',
        description: 'Superadmin only. Creates another superadmin or a normal platform admin. A temporary password is generated and emailed. No company is created.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterAdminRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Administrator registered', content: new OA\JsonContent(ref: '#/components/schemas/UserCreatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not a superadmin', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function registerAdmin(): void {}

    #[OA\Get(
        path: '/auth/me',
        operationId: 'me',
        tags: ['Auth'],
        summary: 'Current authenticated session',
        description: 'Returns the current user, companies, current company, membership, and permissions. Token is omitted.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Current session', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Account suspended', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me(): void {}

    #[OA\Post(
        path: '/auth/logout',
        operationId: 'logout',
        tags: ['Auth'],
        summary: 'Revoke the current token',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function logout(): void {}

    #[OA\Post(
        path: '/auth/logout-all',
        operationId: 'logoutAll',
        tags: ['Auth'],
        summary: 'Revoke all tokens for the user',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'All sessions revoked', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function logoutAll(): void {}

    #[OA\Post(
        path: '/auth/company/switch',
        operationId: 'switchCompany',
        tags: ['Auth'],
        summary: 'Switch current company context',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SwitchCompanyRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Company switched', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not a member or company inactive', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function switchCompany(): void {}

    #[OA\Put(
        path: '/auth/password',
        operationId: 'updatePasswordPut',
        tags: ['Auth'],
        summary: 'Change password',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdatePasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Password updated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updatePasswordPut(): void {}

    #[OA\Post(
        path: '/auth/password',
        operationId: 'updatePasswordPost',
        tags: ['Auth'],
        summary: 'Change password (POST alias)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdatePasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Password updated', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function updatePasswordPost(): void {}

    #[OA\Post(
        path: '/auth/email/resend',
        operationId: 'resendVerification',
        tags: ['Auth'],
        summary: 'Resend email verification',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Verification email sent, or already verified', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function resendVerification(): void {}
}

#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['first_name', 'last_name', 'business_name', 'email', 'phone', 'password', 'password_confirmation', 'address'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string', maxLength: 255, example: 'Jane'),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 255, example: 'Doe'),
        new OA\Property(property: 'business_name', type: 'string', maxLength: 255, example: 'ABC Internet Services'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'jane@example.com'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 50, example: '0700123456'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, format: 'password', example: 'password123'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
        new OA\Property(property: 'address', type: 'string', example: 'Dar es Salaam, Tanzania'),
        new OA\Property(property: 'portal_subdomain', type: 'string', nullable: true, maxLength: 63, example: 'abc-internet', description: 'Lowercase slug. Auto-assigned from business name when omitted.'),
    ]
)]
#[OA\Schema(
    schema: 'RegisterAdminRequest',
    required: ['first_name', 'last_name', 'email', 'phone', 'account_type'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string', maxLength: 255, example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 255, example: 'Admin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'admin@example.com'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 50, example: '0700123456'),
        new OA\Property(property: 'account_type', type: 'string', enum: ['superadmin', 'admin'], example: 'admin'),
    ]
)]
#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
        new OA\Property(property: 'device_name', type: 'string', nullable: true, maxLength: 255, example: 'chrome-desktop'),
    ]
)]
#[OA\Schema(
    schema: 'ForgotPasswordRequest',
    required: ['email'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
    ]
)]
#[OA\Schema(
    schema: 'UpdatePasswordRequest',
    required: ['current_password', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, format: 'password'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
    ]
)]
#[OA\Schema(
    schema: 'ResetPasswordRequest',
    required: ['token', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, format: 'password'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
    ]
)]
#[OA\Schema(
    schema: 'SwitchCompanyRequest',
    required: ['company_id'],
    properties: [
        new OA\Property(property: 'company_id', type: 'integer', example: 1),
    ]
)]
class AuthRequestSchemas {}
