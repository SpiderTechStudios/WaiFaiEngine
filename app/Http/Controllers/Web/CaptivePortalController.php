<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortalPaymentResource;
use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\PaymentTransaction;
use App\Models\StationPlan;
use App\Services\CaptiveSessionService;
use App\Services\PaymentService;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Backend-rendered captive portal.
 *
 * Reached from the WiFiDog login redirect:
 *   GET /connect?subdomain={company}&router={router}&session={captive-token}
 *
 * Everything happens on the API host so the full flow (login → pay → auth)
 * is traceable from one place (storage/logs/wifidog.log + laravel.log).
 */
class CaptivePortalController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private VoucherService $voucherService,
        private CaptiveSessionService $captiveSessionService,
    ) {}

    public function show(Request $request): View
    {
        $company = $this->resolveCompany($request);
        $session = $this->resolveCaptiveSession($company, $request);
        $router = $this->resolveRouter($company, $request, $session);
        $plans = $this->plansFor($company, $router);

        return view('captive.connect', [
            'company' => $company,
            'router' => $router,
            'plans' => $plans,
            'session' => $session,
            'sessionToken' => $session?->token,
            'authenticated' => (bool) $session?->isAuthenticated(),
            'gatewayAuthUrl' => $session?->isAuthenticated()
                ? $this->captiveSessionService->gatewayAuthRedirectUrl($session)
                : null,
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        $validated = $request->validate([
            'internet_plan_id' => ['required', 'integer'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'captive_session' => ['nullable', 'string', 'size:64'],
            'router' => ['nullable', 'string', 'max:64'],
        ]);

        $payment = $this->paymentService->createForPortal($company, [
            'internet_plan_id' => $validated['internet_plan_id'],
            'customer_phone' => $validated['customer_phone'],
            'customer_name' => $validated['customer_name'] ?? null,
            'captive_session' => $validated['captive_session'] ?? null,
        ]);

        return redirect()->route('captive.payment', array_filter([
            'payment' => $payment->id,
            'subdomain' => $company->subdomain,
            'session' => $validated['captive_session'] ?? null,
            'router' => $validated['router'] ?? null,
        ]));
    }

    public function payment(Request $request, int $payment): View
    {
        $company = $this->resolveCompany($request);

        $paymentModel = PaymentTransaction::query()
            ->with('internetPlan')
            ->where('company_id', $company->id)
            ->findOrFail($payment);

        $paymentModel = $this->paymentService->refreshPortalPaymentStatus($paymentModel);

        /** @var array<string, mixed> $payload */
        $payload = (new PortalPaymentResource($paymentModel))->resolve();

        return view('captive.result', [
            'company' => $company,
            'status' => $payload['status'],
            'nextAction' => $payload['next_action'],
            'gatewayAuthUrl' => $payload['gateway_auth_url'],
            'captiveStatus' => $payload['captive_status'],
            'package' => $payload['package'] ?? null,
            'payment' => $paymentModel,
            'sessionToken' => (string) $request->query('session', ''),
            'router' => (string) $request->query('router', ''),
            'voucher' => null,
        ]);
    }

    public function voucher(Request $request): View
    {
        $company = $this->resolveCompany($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'captive_session' => ['nullable', 'string', 'size:64'],
            'mac_address' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $this->voucherService->redeemByCode($company, $validated);
        $grant = $result['access_grant'];
        $gatewayAuthUrl = null;

        if (! empty($validated['captive_session'])) {
            $captive = $this->captiveSessionService->findByToken($validated['captive_session']);

            if ($captive && (int) $captive->company_id === (int) $company->id) {
                if (! $captive->isAuthenticated()) {
                    $captive = $this->captiveSessionService->authenticate($captive, [
                        'access_grant_id' => $grant->id,
                        'mac_address' => $validated['mac_address'] ?? null,
                    ]);
                }

                $gatewayAuthUrl = $this->captiveSessionService->gatewayAuthRedirectUrl($captive);
            }
        }

        return view('captive.result', [
            'company' => $company,
            'status' => 'paid',
            'nextAction' => $gatewayAuthUrl ? 'open_gateway_auth_url' : null,
            'gatewayAuthUrl' => $gatewayAuthUrl,
            'captiveStatus' => $gatewayAuthUrl ? 'authenticated' : null,
            'package' => $result['voucher']->internetPlan ? [
                'id' => $result['voucher']->internetPlan->id,
                'name' => $result['voucher']->internetPlan->name,
                'price' => $result['voucher']->internetPlan->price,
            ] : null,
            'payment' => null,
            'sessionToken' => $validated['captive_session'] ?? '',
            'router' => (string) $request->query('router', ''),
            'voucher' => $grant,
        ]);
    }

    /**
     * Active plans for the company. When the router is attached to a station
     * that has specific plans, only those are offered; otherwise all active
     * company plans are offered.
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
