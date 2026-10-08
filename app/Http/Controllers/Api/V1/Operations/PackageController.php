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
        $company = $this->currentCompany();

        $query = InternetPlan::query()->where('company_id', $company->id);

        $status = strtolower((string) $request->query('status', ''));
        if ($status !== '' && $status !== 'all' && in_array($status, InternetPlan::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('badge', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $paginated = $query
            ->ordered()
            ->paginate($request->integer('per_page', 15));

        $sales = $this->packageService->salesByPackage($company);
        foreach ($paginated->items() as $plan) {
            $this->attachSales($plan, $sales);
        }

        return $this->success(
            $this->paginated($paginated, PackageResource::collection($paginated->items())->resolve()),
            'Packages retrieved',
        );
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->success(
            $this->packageService->summary(
                $this->currentCompany(),
                (string) $request->query('period', '30d'),
            ),
            'Package summary retrieved',
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

        return $this->success((new PackageResource($package))->resolve(), 'Package retrieved');
    }

    public function update(StorePackageRequest $request, InternetPlan $package): JsonResponse
    {
        $this->assertCompany($package->company_id);
        $package = $this->packageService->update($package, $request->validated(), $request->user());

        return $this->success((new PackageResource($package))->resolve(), 'Package updated');
    }

    public function duplicate(Request $request, InternetPlan $package): JsonResponse
    {
        $this->assertCompany($package->company_id);
        $copy = $this->packageService->duplicate($package, $request->user());

        return $this->success((new PackageResource($copy))->resolve(), 'Package duplicated', 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $company = $this->currentCompany();
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        $owned = InternetPlan::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->count();

        if ($owned !== count($ids)) {
            abort(422, 'Some packages do not belong to this company.');
        }

        $plans = $this->packageService->reorder($company, $ids, $request->user());

        return $this->success(
            ['items' => PackageResource::collection($plans)->resolve()],
            'Package order updated',
        );
    }

    public function destroy(Request $request, InternetPlan $package): JsonResponse
    {
        $this->assertCompany($package->company_id);

        $usage = $this->packageService->usageCounts($package);

        if ($usage['payments'] > 0 || $usage['vouchers'] > 0) {
            return response()->json([
                'status' => false,
                'code' => 409,
                'message' => sprintf(
                    'Package has %d payment%s and %d voucher%s. Deactivate it instead.',
                    $usage['payments'],
                    $usage['payments'] === 1 ? '' : 's',
                    $usage['vouchers'],
                    $usage['vouchers'] === 1 ? '' : 's',
                ),
                'data' => [
                    'payments' => $usage['payments'],
                    'vouchers' => $usage['vouchers'],
                ],
            ], 409);
        }

        $this->packageService->delete($package, $request->user());

        return $this->success([], 'Package deleted');
    }

    /**
     * @param  array<int, array{sold: int, revenue: float, last_sold_at: string|null}>  $sales
     */
    private function attachSales(InternetPlan $plan, array $sales): void
    {
        $row = $sales[$plan->id] ?? null;

        $plan->setAttribute('sold', $row['sold'] ?? 0);
        $plan->setAttribute('revenue', $row['revenue'] ?? 0.0);
        $plan->setAttribute('last_sold_at', $row['last_sold_at'] ?? null);
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
