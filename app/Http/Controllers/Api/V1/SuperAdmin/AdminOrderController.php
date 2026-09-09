<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\MarketplaceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(private MarketplaceOrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Order::query()
            ->with(['items', 'company', 'payments'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, OrderResource::collection($paginated->items())->resolve()),
            'Orders retrieved',
        );
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success(
            (new OrderResource($order->load(['items', 'company', 'payments'])))->resolve(),
            'Order retrieved',
        );
    }

    public function inTransit(Order $order): JsonResponse
    {
        return $this->success(
            (new OrderResource($this->orders->markInTransit($order)))->resolve(),
            'Order marked in transit',
        );
    }

    public function delivered(Order $order): JsonResponse
    {
        return $this->success(
            (new OrderResource($this->orders->markDelivered($order)))->resolve(),
            'Order marked delivered',
        );
    }

    public function cancel(Order $order): JsonResponse
    {
        return $this->success(
            (new OrderResource($this->orders->cancel($order)))->resolve(),
            'Order cancelled',
        );
    }
}
