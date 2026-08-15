<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class AuthDocumentation
{
    #[OA\Post(
        path: '/register',
        operationId: 'legacyRegister',
        tags: ['Auth'],
        summary: 'Register (legacy route)',
        description: 'Same as POST /auth/register. Kept for existing clients.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Registered', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function legacyRegister(): void {}

    #[OA\Post(
        path: '/auth/register',
        operationId: 'register',
        tags: ['Auth'],
        summary: 'Register a user',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Registered', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/auth/login',
        operationId: 'login',
        tags: ['Auth'],
        summary: 'Login',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'device_name', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Logged in', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionResponse')),
            new OA\Response(response: 422, description: 'Invalid credentials or inactive account', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function login(): void {}

    #[OA\Get(
        path: '/auth/me',
        operationId: 'me',
        tags: ['Auth'],
        summary: 'Current authenticated user',
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
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['company_id'],
                properties: [
                    new OA\Property(property: 'company_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Company switched', content: new OA\JsonContent(ref: '#/components/schemas/AuthSessionResponse')),
            new OA\Response(response: 403, description: 'Not a member or company inactive', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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
        ]
    )]
    public function updatePasswordPost(): void {}

    #[OA\Post(
        path: '/auth/forgot-password',
        operationId: 'forgotPassword',
        tags: ['Auth'],
        summary: 'Request a password reset',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [new OA\Property(property: 'email', type: 'string', format: 'email')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Generic success to avoid account enumeration', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
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
        ]
    )]
    public function verifyEmail(): void {}

    #[OA\Post(
        path: '/auth/email/resend',
        operationId: 'resendVerification',
        tags: ['Auth'],
        summary: 'Resend email verification',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Verification email sent', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ]
    )]
    public function resendVerification(): void {}
}

#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string', minLength: 8),
        new OA\Property(property: 'password_confirmation', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'UpdatePasswordRequest',
    required: ['current_password', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string'),
        new OA\Property(property: 'password', type: 'string', minLength: 8),
        new OA\Property(property: 'password_confirmation', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'ResetPasswordRequest',
    required: ['token', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string', minLength: 8),
        new OA\Property(property: 'password_confirmation', type: 'string'),
    ]
)]
class AuthRequestSchemas {}
