<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\MarketplaceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private MarketplaceOrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Order::query()
            ->with(['items', 'payments'])
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, OrderResource::collection($paginated->items())->resolve()),
            'Orders retrieved',
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->assertOwned($order);

        return $this->success(
            (new OrderResource($order->load(['items', 'payments'])))->resolve(),
            'Order retrieved',
        );
    }

    public function confirmDelivery(Order $order): JsonResponse
    {
        $this->assertOwned($order);
        $order = $this->orders->markDelivered($order);

        return $this->success((new OrderResource($order))->resolve(), 'Order marked delivered');
    }

    private function assertOwned(Order $order): void
    {
        if ((int) $order->company_id !== (int) $this->currentCompany()->id) {
            abort(404, 'Order not found.');
        }
    }
}
