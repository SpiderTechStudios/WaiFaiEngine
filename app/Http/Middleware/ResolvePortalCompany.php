<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\PortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortalCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = (string) $request->route('subdomain');

        $company = Company::query()
            ->where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();

        if (! $company) {
            abort(404, 'Portal not found.');
        }

        app()->instance(PortalContext::class, new PortalContext(company: $company));

        return $next($request);
    }
}
