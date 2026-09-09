<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PlatformPaymentResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\CartService;
use App\Services\MarketplaceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private MarketplaceOrderService $orders,
    ) {}

    public function show(): JsonResponse
    {
        $cart = $this->cartService->current($this->currentCompany(), request()->user());

        return $this->success((new CartResource($cart))->resolve(), 'Cart retrieved');
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartService->add(
            $this->currentCompany(),
            $request->user(),
            (int) $data['device_id'],
            (int) ($data['quantity'] ?? 1),
        );

        return $this->success((new CartResource($cart))->resolve(), 'Item added to cart');
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartService->updateQuantity(
            $this->currentCompany(),
            $cartItem,
            (int) $data['quantity'],
        );

        return $this->success((new CartResource($cart))->resolve(), 'Cart quantity updated');
    }

    public function remove(CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartService->remove($this->currentCompany(), $cartItem);

        return $this->success((new CartResource($cart))->resolve(), 'Item removed from cart');
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->clear($this->currentCompany(), $request->user());

        return $this->success((new CartResource($cart))->resolve(), 'Cart cleared');
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fulfillment_method' => ['nullable', 'string', Rule::in([Order::METHOD_DELIVERY, Order::METHOD_PICKUP])],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->orders->checkout($this->currentCompany(), $request->user(), $data);

        return $this->success([
            'order' => (new OrderResource($result['order']))->resolve(),
            'payment' => (new PlatformPaymentResource($result['payment']))->resolve(),
        ], 'Order created and payment initiated', 201);
    }
}
