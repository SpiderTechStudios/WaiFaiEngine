<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreRouterRequest;
use App\Http\Resources\RouterResource;
use App\Models\NetworkDevice;
use App\Services\RouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouterController extends Controller
{
    public function __construct(private RouterService $routerService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = NetworkDevice::query()
            ->with('networkStation.location')
            ->where('company_id', $this->currentCompany()->id)
            ->where('type', 'router')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, RouterResource::collection($paginated->items())->resolve()),
            'Routers retrieved',
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
        $router->load('networkStation.location');

        return $this->success((new RouterResource($router))->resolve(), 'Router retrieved');
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

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
