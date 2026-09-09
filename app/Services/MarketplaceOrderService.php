<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceOrderService
{
    public function __construct(
        private CartService $cartService,
        private PlatformPaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{order: Order, payment: PlatformPayment}
     */
    public function checkout(Company $company, User $user, array $data): array
    {
        return DB::transaction(function () use ($company, $user, $data) {
            $cart = $this->cartService->current($company, $user);

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Your cart is empty.'],
                ]);
            }

            $lineItems = [];
            $total = 0.0;

            foreach ($cart->items as $item) {
                $device = $this->cartService->purchasableDevice($item->device_id);
                $this->cartService->assertQuantity($device, (int) $item->quantity);
                $lineTotal = (float) $device->price * (int) $item->quantity;
                $total += $lineTotal;
                $lineItems[] = [
                    'device' => $device,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $device->price,
                    'line_total' => $lineTotal,
                ];
            }

            $order = Order::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'reference' => 'ORD-'.strtoupper(Str::random(10)),
                'status' => Order::STATUS_PENDING,
                'payment_status' => PlatformPayment::STATUS_PENDING,
                'fulfillment_method' => $data['fulfillment_method'] ?? Order::METHOD_DELIVERY,
                'total_amount' => $total,
                'currency' => config('platform.currency', 'TZS'),
                'phone' => $data['phone'] ?? $company->phone,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lineItems as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'device_id' => $line['device']->id,
                    'name' => $line['device']->name,
                    'sku' => $line['device']->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);
            }

            $cart->items()->delete();

            $payment = $this->payments->startDevicePurchasePayment($order->fresh('items'), [
                'payment_method' => $data['payment_method'] ?? 'mobile_money',
                'phone' => $data['phone'] ?? $company->phone,
            ]);

            return [
                'order' => $order->fresh(['items.device', 'payments']),
                'payment' => $payment,
            ];
        });
    }

    public function markPaid(Order $order): Order
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'order' => ['Cancelled orders cannot be paid.'],
            ]);
        }

        if ($order->payment_status === PlatformPayment::STATUS_PAID) {
            return $order;
        }

        foreach ($order->items as $item) {
            if (! $item->device_id) {
                continue;
            }

            $device = $item->device()->lockForUpdate()->first();
            if ($device && (int) $device->stock_quantity >= (int) $item->quantity) {
                $device->decrement('stock_quantity', (int) $item->quantity);
            }
        }

        $order->forceFill([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => PlatformPayment::STATUS_PAID,
            'paid_at' => now(),
        ])->save();

        return $order->fresh(['items', 'payments']);
    }

    public function markInTransit(Order $order): Order
    {
        $this->assertStatus($order, [Order::STATUS_PROCESSING], 'Only paid orders can be marked in transit.');

        $order->forceFill([
            'status' => Order::STATUS_IN_TRANSIT,
            'shipped_at' => now(),
        ])->save();

        return $order->fresh(['items', 'company', 'payments']);
    }

    public function markDelivered(Order $order): Order
    {
        $allowed = [Order::STATUS_PROCESSING, Order::STATUS_IN_TRANSIT];
        $this->assertStatus($order, $allowed, 'This order cannot be marked delivered yet.');

        $order->forceFill([
            'status' => Order::STATUS_DELIVERED,
            'delivered_at' => now(),
        ])->save();

        return $order->fresh(['items', 'company', 'payments']);
    }

    public function cancel(Order $order): Order
    {
        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'order' => ['This order cannot be cancelled.'],
            ]);
        }

        if ($order->payment_status === PlatformPayment::STATUS_PAID) {
            foreach ($order->items as $item) {
                if ($item->device_id) {
                    $item->device()->increment('stock_quantity', (int) $item->quantity);
                }
            }
        }

        $order->payments()
            ->where('status', PlatformPayment::STATUS_PENDING)
            ->update([
                'status' => PlatformPayment::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

        $order->forceFill([
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => $order->payment_status === PlatformPayment::STATUS_PAID
                ? PlatformPayment::STATUS_PAID
                : PlatformPayment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        return $order->fresh(['items', 'payments']);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertStatus(Order $order, array $allowed, string $message): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'order' => [$message],
            ]);
        }
    }
}
