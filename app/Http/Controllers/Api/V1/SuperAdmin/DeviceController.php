<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreDeviceRequest;
use App\Http\Requests\SuperAdmin\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private DeviceCatalogService $deviceService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Device::query()
            ->with(['brand', 'category'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('device_category_id'), fn ($query) => $query->where('device_category_id', $request->integer('device_category_id')))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, DeviceResource::collection($paginated->items())->resolve()),
            'Devices retrieved',
        );
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $device = $this->deviceService->create($request->validated(), $request->file('image'));

        return $this->success(
            (new DeviceResource($device))->resolve(),
            'Device created',
            201,
        );
    }

    public function show(Device $device): JsonResponse
    {
        return $this->success(
            (new DeviceResource($device->load(['brand', 'category'])))->resolve(),
            'Device retrieved',
        );
    }

    public function update(UpdateDeviceRequest $request, Device $device): JsonResponse
    {
        $device = $this->deviceService->update($device, $request->validated(), $request->file('image'));

        return $this->success(
            (new DeviceResource($device))->resolve(),
            'Device updated',
        );
    }

    public function destroy(Device $device): JsonResponse
    {
        $this->deviceService->delete($device);

        return $this->success([], 'Device deleted');
    }
}
