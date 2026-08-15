<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_superadmin) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Platform administrator access is required.',
            ], 403);
        }

        return $next($request);
    }
}
