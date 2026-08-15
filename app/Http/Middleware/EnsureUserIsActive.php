<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user->refresh();
        }

        if ($user && in_array($user->status, ['suspended', 'inactive'], true)) {
            $user->tokens()->delete();

            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'This account is not allowed to access the platform.',
            ], 403);
        }

        return $next($request);
    }
}
