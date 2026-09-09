<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\EnsureCurrentCompany;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolvePortalCompany;
use App\Http\Middleware\SetCompanyContext;
use App\Support\ApiErrorMessage;
use App\Support\CompanyContext;
use App\Support\PortalContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'company.context' => SetCompanyContext::class,
            'company.access' => EnsureCompanyAccess::class,
            'company.required' => EnsureCurrentCompany::class,
            'superadmin' => EnsureSuperAdmin::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'permission' => EnsurePermission::class,
            'portal.company' => ResolvePortalCompany::class,
            'subscription.active' => EnsureActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation Error',
                    'data' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'code' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => ApiErrorMessage::from($e, 'Resource not found.'),
                ], 404);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $fallback = $e->getStatusCode() === 404 ? 'Resource not found.' : 'Request failed.';

                return response()->json([
                    'status' => false,
                    'code' => $e->getStatusCode(),
                    'message' => ApiErrorMessage::from($e, $fallback),
                ], $e->getStatusCode());
            }
        });
    })
    ->withBindings([
        CompanyContext::class => fn () => new CompanyContext,
        PortalContext::class => fn () => new PortalContext,
    ])
    ->create();
