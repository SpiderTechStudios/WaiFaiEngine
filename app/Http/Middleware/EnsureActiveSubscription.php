<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_superadmin) {
            return $next($request);
        }

        $company = app(CompanyContext::class)->company;

        if (! $company) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'No active company is selected for this session.',
            ], 403);
        }

        if (! $this->subscriptionService->syncAndIsActive($company)) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Your WaiFai subscription is not active. Renew billing to continue.',
                'data' => [
                    'subscription_status' => $company->fresh()->subscription_status,
                    'period_ends_at' => $company->fresh()->subscription_period_ends_at,
                ],
            ], 403);
        }

        return $next($request);
    }
}
