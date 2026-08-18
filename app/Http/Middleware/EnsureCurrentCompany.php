<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class);
        $user = $request->user();

        if (! $context->company) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'No active company is selected for this session.',
            ], 403);
        }

        if ($user?->is_superadmin) {
            return $next($request);
        }

        if (! $context->isActive()) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'You do not have access to this company.',
            ], 403);
        }

        return $next($request);
    }
}
