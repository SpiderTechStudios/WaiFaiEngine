<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreRouterRequest;
use App\Http\Resources\RouterResource;
use App\Models\Company;
use App\Models\NetworkDevice;
use App\Services\RouterMetricsService;
use App\Services\RouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RouterController extends Controller
{
    public function __construct(
        private RouterService $routerService,
        private RouterMetricsService $routerMetrics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->currentCompany();

        $paginated = NetworkDevice::query()
            ->with('networkStation.location')
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        $this->attachLiveMetrics($company, $paginated->items());

        return $this->success(
            $this->paginated($paginated, RouterResource::collection($paginated->items())->resolve()),
            'Routers retrieved',
        );
    }

    public function summary(): JsonResponse
    {
        return $this->success(
            $this->routerMetrics->summary($this->currentCompany()),
            'Router summary retrieved',
        );
    }

    public function store(StoreRouterRequest $request): JsonResponse
    {
        $router = $this->routerService->create($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new RouterResource($router))->resolve(), 'Router added', 201);
    }

    public function show(NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);
        $company = $this->currentCompany();

        $router->load('networkStation.location');
        $timezone = $company->reportingTimezone();
        $now = Carbon::now($timezone);

        $clients = $this->routerMetrics->clientsNow($company, [$router->id]);
        $today = $this->routerMetrics->revenueByRouter($company, $now->copy()->startOfDay(), $now);
        $week = $this->routerMetrics->revenueByRouter($company, $now->copy()->startOfWeek(), $now);
        $month = $this->routerMetrics->revenueByRouter($company, $now->copy()->startOfMonth(), $now);

        $router->setAttribute('clients_now', $clients[$router->id] ?? 0);
        $router->setAttribute('revenue_today', $today[$router->id]['amount'] ?? 0.0);
        $router->setAttribute('payments_today', $today[$router->id]['count'] ?? 0);
        $router->setAttribute('revenue', [
            'today' => $today[$router->id]['amount'] ?? 0.0,
            'week' => $week[$router->id]['amount'] ?? 0.0,
            'month' => $month[$router->id]['amount'] ?? 0.0,
            'currency' => (string) config('platform.currency', 'TZS'),
        ]);

        return $this->success((new RouterResource($router))->resolve(), 'Router retrieved');
    }

    public function events(NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);

        return $this->success($this->routerService->events($router), 'Router events retrieved');
    }

    public function test(NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);

        return $this->success($this->routerService->test($router), 'Router connection tested');
    }

    public function reboot(Request $request, NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);
        $result = $this->routerService->reboot($router, $request->user());

        return $this->success($result, 'Reboot requested');
    }

    public function setup(NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);

        return $this->success($this->routerService->setup($router), 'Router setup retrieved');
    }

    public function update(StoreRouterRequest $request, NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);
        $router = $this->routerService->update($router, $request->validated(), $request->user());

        return $this->success((new RouterResource($router))->resolve(), 'Router updated');
    }

    public function destroy(Request $request, NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);
        $this->routerService->delete($router, $request->user());

        return $this->success([], 'Router removed');
    }

    public function sync(Request $request, NetworkDevice $router): JsonResponse
    {
        $this->assertCompany($router->company_id);
        $router = $this->routerService->syncFromRuijie($router, $request->user());
        $router->load('networkStation.location');

        return $this->success((new RouterResource($router))->resolve(), 'Router synced from Ruijie Cloud');
    }

    /**
     * Attach clients_now / revenue_today / payments_today to each router row.
     *
     * @param  array<int, NetworkDevice>  $routers
     */
    private function attachLiveMetrics(Company $company, array $routers): void
    {
        $routerIds = array_map(fn (NetworkDevice $router) => $router->id, $routers);
        $clients = $this->routerMetrics->clientsNow($company, $routerIds);

        $timezone = $company->reportingTimezone();
        $now = Carbon::now($timezone);
        $revenue = $this->routerMetrics->revenueByRouter($company, $now->copy()->startOfDay(), $now);

        foreach ($routers as $router) {
            $router->setAttribute('clients_now', $clients[$router->id] ?? 0);
            $router->setAttribute('revenue_today', $revenue[$router->id]['amount'] ?? 0.0);
            $router->setAttribute('payments_today', $revenue[$router->id]['count'] ?? 0);
        }
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
