<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\UserCompany;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $request->route('company');

        if (! $company instanceof Company) {
            return $next($request);
        }

        if ($user?->is_superadmin) {
            $membership = UserCompany::query()
                ->with('role.permissions')
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->first();

            app()->instance(CompanyContext::class, new CompanyContext(
                user: $user,
                company: $company,
                membership: $membership,
                role: $membership?->role,
            ));

            return $next($request);
        }

        $membership = UserCompany::query()
            ->with('role.permissions')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->first();

        if (! $membership || $company->status !== 'active') {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'You do not have access to this company.',
            ], 403);
        }

        app()->instance(CompanyContext::class, new CompanyContext(
            user: $user,
            company: $company,
            membership: $membership,
            role: $membership->role,
        ));

        return $next($request);
    }
}
