<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\Order;
use App\Services\PlatformPaymentService;
use Tests\TestCase;

class MarketplaceCartTest extends TestCase
{
    public function test_company_can_cart_checkout_and_superadmin_can_fulfill(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner');
        $headers = $this->authHeaders($owner);

        $brand = Brand::query()->create([
            'name' => 'Cart Brand',
            'slug' => 'cart-brand',
            'is_active' => true,
        ]);
        $category = DeviceCategory::query()->create([
            'name' => 'Cart Category',
        ]);
        $device = Device::query()->create([
            'name' => 'Office Router',
            'slug' => 'office-router',
            'sku' => 'SKU-CART-1',
            'device_category_id' => $category->id,
            'brand_id' => $brand->id,
            'price' => 100000,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/marketplace/devices')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $device->id);

        $cart = $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', [
                'device_id' => $device->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2);

        $itemId = $cart->json('data.items.0.id');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.total_amount', '100000.00');

        $checkout = $this->withHeaders($headers)
            ->postJson('/api/v1/cart/checkout', [
                'fulfillment_method' => 'delivery',
                'phone' => '0712345678',
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.payment.purpose', 'device_purchase');

        $this->withHeaders($headers)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items', []);

        $paymentId = $checkout->json('data.payment.payment_id');
        $orderId = $checkout->json('data.order.id');

        app(PlatformPaymentService::class)->markPaid(
            \App\Models\PlatformPayment::query()->findOrFail($paymentId)
        );

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);
        $this->assertSame(4, $device->fresh()->stock_quantity);

        $admin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/superadmin/orders/'.$orderId.'/in-transit')
            ->assertOk()
            ->assertJsonPath('data.status', 'in-transit');

        $this->withHeaders($headers)
            ->postJson('/api/v1/orders/'.$orderId.'/confirm-delivery')
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }
}
