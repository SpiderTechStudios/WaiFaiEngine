<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreDeviceCategoryRequest;
use App\Http\Requests\SuperAdmin\UpdateDeviceCategoryRequest;
use App\Http\Resources\DeviceCategoryResource;
use App\Models\DeviceCategory;
use App\Services\DeviceCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceCategoryController extends Controller
{
    public function __construct(private DeviceCategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = DeviceCategory::query()
            ->withCount('devices')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, DeviceCategoryResource::collection($paginated->items())->resolve()),
            'Device categories retrieved',
        );
    }

    public function store(StoreDeviceCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success(
            (new DeviceCategoryResource($category->loadCount('devices')))->resolve(),
            'Device category created',
            201,
        );
    }

    public function show(DeviceCategory $deviceCategory): JsonResponse
    {
        return $this->success(
            (new DeviceCategoryResource($deviceCategory->loadCount('devices')))->resolve(),
            'Device category retrieved',
        );
    }

    public function update(UpdateDeviceCategoryRequest $request, DeviceCategory $deviceCategory): JsonResponse
    {
        $category = $this->categoryService->update($deviceCategory, $request->validated());

        return $this->success(
            (new DeviceCategoryResource($category->loadCount('devices')))->resolve(),
            'Device category updated',
        );
    }

    public function destroy(DeviceCategory $deviceCategory): JsonResponse
    {
        $this->categoryService->delete($deviceCategory);

        return $this->success([], 'Device category deleted');
    }
}
