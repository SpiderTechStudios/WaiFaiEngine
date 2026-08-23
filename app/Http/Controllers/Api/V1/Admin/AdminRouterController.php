<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStoreRouterRequest;
use App\Http\Resources\AdminRouterResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\RouterResource;
use App\Models\Company;
use App\Models\NetworkDevice;
use App\Services\RouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRouterController extends Controller
{
    public function __construct(private RouterService $routerService) {}

    public function index(Request $request): JsonResponse
    {
        $query = NetworkDevice::query()
            ->with(['company', 'networkStation.location'])
            ->where('type', 'router');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('gateway_type')) {
            $query->where('gateway_type', $request->string('gateway_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $paginated = $query->latest('id')->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, AdminRouterResource::collection($paginated->items())->resolve()),
            'Routers retrieved',
        );
    }

    public function store(AdminStoreRouterRequest $request): JsonResponse
    {
        $company = Company::query()->findOrFail($request->integer('company_id'));
        $router = $this->routerService->create($company, $request->validated(), $request->user());
        $router->load('company', 'networkStation.location');

        return $this->success(
            (new AdminRouterResource($router))->resolve(),
            'Router added for client',
            201,
        );
    }

    public function show(NetworkDevice $router): JsonResponse
    {
        $router = $this->assertRouter($router);
        $router->load('company', 'networkStation.location');

        return $this->success([
            'router' => (new RouterResource($router))->resolve(),
            'company' => (new CompanyResource($router->company))->resolve(),
        ], 'Router and client retrieved');
    }

    public function update(AdminStoreRouterRequest $request, NetworkDevice $router): JsonResponse
    {
        $router = $this->assertRouter($router);
        $router = $this->routerService->update($router, $request->validated(), $request->user());
        $router->load('company', 'networkStation.location');

        return $this->success(
            (new AdminRouterResource($router))->resolve(),
            'Router updated',
        );
    }

    public function destroy(Request $request, NetworkDevice $router): JsonResponse
    {
        $router = $this->assertRouter($router);
        $this->routerService->delete($router, $request->user());

        return $this->success([], 'Router removed');
    }

    public function sync(Request $request, NetworkDevice $router): JsonResponse
    {
        $router = $this->assertRouter($router);
        $router = $this->routerService->syncFromRuijie($router, $request->user());
        $router->load('company', 'networkStation.location');

        return $this->success(
            (new AdminRouterResource($router))->resolve(),
            'Router synced from Ruijie Cloud',
        );
    }

    private function assertRouter(NetworkDevice $router): NetworkDevice
    {
        if ($router->type !== 'router') {
            abort(404);
        }

        return $router;
    }
}
