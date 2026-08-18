<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StorePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\InternetPlan;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(private PackageService $packageService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = InternetPlan::query()
            ->with('prices')
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, PackageResource::collection($paginated->items())->resolve()),
            'Packages retrieved',
        );
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packageService->create($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new PackageResource($package))->resolve(), 'Package created', 201);
    }

    public function show(InternetPlan $package): JsonResponse
    {
        $this->assertCompany($package->company_id);

        return $this->success((new PackageResource($package->load('prices')))->resolve(), 'Package retrieved');
    }

    public function update(StorePackageRequest $request, InternetPlan $package): JsonResponse
    {
        $this->assertCompany($package->company_id);
        $package = $this->packageService->update($package, $request->validated(), $request->user());

        return $this->success((new PackageResource($package))->resolve(), 'Package updated');
    }

    public function destroy(Request $request, InternetPlan $package): JsonResponse
    {
        $this->assertCompany($package->company_id);
        $this->packageService->delete($package, $request->user());

        return $this->success([], 'Package deleted');
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
