<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginated = Device::query()
            ->with(['brand', 'category'])
            ->where('is_active', true)
            ->whereNotNull('price')
            ->where('stock_quantity', '>', 0)
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
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, DeviceResource::collection($paginated->items())->resolve()),
            'Marketplace devices retrieved',
        );
    }

    public function show(Device $device): JsonResponse
    {
        if (! $device->is_active || $device->price === null) {
            abort(404, 'Device not found.');
        }

        return $this->success(
            (new DeviceResource($device->load(['brand', 'category'])))->resolve(),
            'Marketplace device retrieved',
        );
    }
}
