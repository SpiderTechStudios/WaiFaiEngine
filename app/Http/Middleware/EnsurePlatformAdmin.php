<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->is_superadmin && ! $user?->is_admin) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Platform administrator access is required.',
            ], 403);
        }

        return $next($request);
    }
}
