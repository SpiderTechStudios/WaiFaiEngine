<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\StationPlan;
use App\Services\CaptiveSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Backend-rendered captive portal (single page).
 *
 * Reached from the WiFiDog login redirect:
 *   GET /connect?subdomain={company}&router={router}&session={captive-token}
 *
 * The page is a single-page UI (initial / subscribe / voucher / redeem) that
 * talks to the existing /api/v1 portal + captive endpoints via JavaScript.
 */
class CaptivePortalController extends Controller
{
    public function __construct(
        private CaptiveSessionService $captiveSessionService,
    ) {}

    public function show(Request $request): View
    {
        $company = $this->resolveCompany($request);
        $session = $this->resolveCaptiveSession($company, $request);
        $router = $this->resolveRouter($company, $request, $session);
        $plans = $this->plansFor($company, $router);

        return view('captive.connect', [
            'portal' => [
                'business_name' => $company->name,
                'welcome_message' => filled($company->captive_portal_welcome_message)
                    ? $company->captive_portal_welcome_message
                    : 'Karibu! Chagua kifurushi chako ili kuendelea kutumia intaneti.',
                'contact_phone' => filled($company->phone) ? $company->phone : '0687181497',
                'brand_color' => filled($company->primary_color) ? $company->primary_color : '#0F4C81',
                'logo_url' => filled($company->logo_url) ? $company->logo_url : null,
                'subdomain' => $company->subdomain,
                'router_name' => $router?->name,
                'session_token' => $session?->token,
                'client_mac' => $session?->client_mac,
                'gateway_auth_url' => $session?->isAuthenticated()
                    ? $this->captiveSessionService->gatewayAuthRedirectUrl($session)
                    : null,
                'packages' => $this->formatPackages($plans),
            ],
        ]);
    }

    /**
     * Active plans for the company. When the router's station has specific
     * plans, only those are offered; otherwise all active company plans.
     *
     * @return Collection<int, InternetPlan>
     */
    private function plansFor(Company $company, ?NetworkDevice $router): Collection
    {
        $query = InternetPlan::query()
            ->where('company_id', $company->id)
            ->where('status', 'active');

        if ($router && $router->network_station_id) {
            $stationPlanIds = StationPlan::query()
                ->where('company_id', $company->id)
                ->where('network_station_id', $router->network_station_id)
                ->where('status', 'active')
                ->pluck('internet_plan_id');

            if ($stationPlanIds->isNotEmpty()) {
                $query->whereIn('id', $stationPlanIds);
            }
        }

        return $query->orderBy('price')->get();
    }

    /**
     * @param  Collection<int, InternetPlan>  $plans
     * @return list<array<string, mixed>>
     */
    private function formatPackages(Collection $plans): array
    {
        return $plans->map(fn (InternetPlan $plan): array => [
            'id' => $plan->id,
            'name' => $plan->name,
            'badge' => $plan->badge,
            'description' => $plan->description,
            'duration' => $plan->duration,
            'duration_unit' => $plan->duration_unit,
            'price' => (float) $plan->price,
        ])->values()->all();
    }

    private function resolveCompany(Request $request): Company
    {
        $subdomain = (string) $request->query('subdomain', '');

        $company = $subdomain === ''
            ? null
            : Company::query()
                ->where('subdomain', $subdomain)
                ->where('status', 'active')
                ->first();

        if (! $company) {
            abort(404, 'Portal not found.');
        }

        return $company;
    }

    private function resolveCaptiveSession(Company $company, Request $request): ?CaptiveSession
    {
        $token = (string) $request->query('session', '');

        if ($token === '') {
            return null;
        }

        $session = $this->captiveSessionService->findByToken($token);

        if (! $session || (int) $session->company_id !== (int) $company->id) {
            return null;
        }

        return $session;
    }

    private function resolveRouter(Company $company, Request $request, ?CaptiveSession $session): ?NetworkDevice
    {
        $router = trim((string) $request->query('router', ''));

        if ($router !== '') {
            $device = ctype_digit($router)
                ? NetworkDevice::query()->where('company_id', $company->id)->find((int) $router)
                : NetworkDevice::query()
                    ->where('company_id', $company->id)
                    ->where('gateway_id', $router)
                    ->first();

            if ($device) {
                return $device;
            }
        }

        if ($session?->network_device_id) {
            return NetworkDevice::query()
                ->where('company_id', $company->id)
                ->find($session->network_device_id);
        }

        return null;
    }
}
