<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreBrandRequest;
use App\Http\Requests\SuperAdmin\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private BrandService $brandService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Brand::query()
            ->withCount('devices')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, BrandResource::collection($paginated->items())->resolve()),
            'Brands retrieved',
        );
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brandService->create($request->validated(), $request->file('logo'));

        return $this->success(
            (new BrandResource($brand->loadCount('devices')))->resolve(),
            'Brand created',
            201,
        );
    }

    public function show(Brand $brand): JsonResponse
    {
        return $this->success(
            (new BrandResource($brand->loadCount('devices')))->resolve(),
            'Brand retrieved',
        );
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->brandService->update($brand, $request->validated(), $request->file('logo'));

        return $this->success(
            (new BrandResource($brand->loadCount('devices')))->resolve(),
            'Brand updated',
        );
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brandService->delete($brand);

        return $this->success([], 'Brand deleted');
    }
}
