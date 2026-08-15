<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $context = app(CompanyContext::class);

        if ($request->user()?->is_superadmin) {
            return $next($request);
        }

        if (! $context->company || ! $context->isActive() || ! $context->hasPermission($permission)) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
