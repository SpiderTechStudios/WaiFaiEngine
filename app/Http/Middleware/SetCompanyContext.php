<?php

namespace App\Http\Middleware;

use App\Models\UserCompany;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $company = $user->currentCompany;

        if (! $company) {
            app()->instance(CompanyContext::class, new CompanyContext(user: $user));

            return $next($request);
        }

        $membership = UserCompany::query()
            ->with('role.permissions')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if (! $membership || $membership->status !== 'active' || $company->status !== 'active') {
            if (! $user->is_superadmin) {
                $user->forceFill(['current_company_id' => null])->save();
                app()->instance(CompanyContext::class, new CompanyContext(user: $user));

                return $next($request);
            }
        }

        app()->instance(CompanyContext::class, new CompanyContext(
            user: $user,
            company: $company,
            membership: $membership,
            role: $membership?->role,
        ));

        return $next($request);
    }
}
