<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Services\PaymentProviderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentProviderTest extends TestCase
{
    public function test_superadmin_can_list_and_manage_providers(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $response = $this->withHeaders($this->authHeaders($superadmin))
            ->getJson('/api/v1/superadmin/payment-providers')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('stub', $slugs);
        $this->assertContains('flutterwave', $slugs);
        $this->assertContains('palmpay', $slugs);
        $this->assertContains('palmpesa', $slugs);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/payment-providers', [
                'name' => 'AzamPay',
                'slug' => 'azampay',
                'supports_payments' => true,
                'supports_payouts' => true,
                'credentials' => [
                    'public_key' => 'pub-test',
                    'secret_key' => 'sec-test',
                ],
            ])->assertCreated()
            ->assertJsonPath('data.slug', 'azampay')
            ->assertJsonMissingPath('data.credentials.secret_key')
            ->assertJsonPath('data.credentials.has_secret_key', true);

        $azam = PaymentProvider::query()->where('slug', 'azampay')->firstOrFail();

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/payment-providers/azampay/default-payments')
            ->assertOk()
            ->assertJsonPath('data.is_default_for_payments', true);

        $this->assertFalse(PaymentProvider::query()->where('slug', 'stub')->value('is_default_for_payments'));
        $this->assertTrue($azam->fresh()->is_default_for_payments);
    }

    public function test_renaming_provider_updates_slug_and_keeps_driver(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/payment-providers', [
                'name' => 'AzamPay',
                'slug' => 'azampay',
                'supports_payments' => true,
                'credentials' => [
                    'secret_key' => 'sec-test',
                ],
            ])->assertCreated();

        $this->withHeaders($this->authHeaders($superadmin))
            ->patchJson('/api/v1/superadmin/payment-providers/azampay', [
                'name' => 'Azam Pay Tanzania',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Azam Pay Tanzania')
            ->assertJsonPath('data.slug', 'azam-pay-tanzania')
            ->assertJsonPath('data.settings.driver', 'azampay');

        $this->assertDatabaseMissing('payment_providers', ['slug' => 'azampay']);
        $this->assertDatabaseHas('payment_providers', [
            'slug' => 'azam-pay-tanzania',
            'name' => 'Azam Pay Tanzania',
        ]);
    }

    public function test_unknown_payment_provider_returns_not_found(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->withHeaders($this->authHeaders($superadmin))
            ->getJson('/api/v1/superadmin/payment-providers/flutter')
            ->assertNotFound()
            ->assertJsonPath('message', 'Payment provider not found.');

        $this->postJson('/api/v1/webhooks/payments/flutter', [])
            ->assertNotFound()
            ->assertJsonPath('message', 'Payment provider not found.');
    }

    public function test_non_superadmin_cannot_manage_providers(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/superadmin/payment-providers')
            ->assertForbidden();
    }

    public function test_registration_uses_default_provider(): void
    {
        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.payment.provider', 'stub')
            ->assertJsonPath('data.payment.purpose', 'platform_subscription');
    }

    public function test_provider_webhook_rejects_invalid_signature(): void
    {
        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->postJson('/api/v1/webhooks/payments/stub', [
            'reference' => $payment->reference,
            'status' => 'paid',
            'amount' => 10000,
            'currency' => 'TZS',
        ], [
            'X-Platform-Payment-Secret' => 'wrong-secret',
        ])->assertUnauthorized();
    }

    public function test_palmpay_can_be_set_as_default_and_used_for_collections(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $palmpay = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPAY)->firstOrFail();

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/payment-providers/palmpay/default-payments')
            ->assertOk()
            ->assertJsonPath('data.is_default_for_payments', true);

        $this->assertTrue($palmpay->fresh()->is_default_for_payments);
        $this->assertFalse(PaymentProvider::query()->where('slug', 'stub')->value('is_default_for_payments'));

        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.payment.provider', 'palmpay');
    }

    public function test_palmpay_webhook_marks_payment_paid_and_returns_plain_success(): void
    {
        $palmpay = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPAY)->firstOrFail();
        $palmpay->forceFill([
            'credentials' => [
                'api_base_url' => 'https://open-gw-daily.palmpay-inc.com',
            ],
            'is_active' => true,
            'supports_payments' => true,
        ])->save();

        app(PaymentProviderService::class)->setDefaultForPayments($palmpay->fresh());

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->assertSame('palmpay', $payment->provider_slug);

        $response = $this->postJson('/api/v1/webhooks/payments/palmpay', [
            'orderId' => $payment->reference,
            'orderNo' => 'PP-ORDER-123',
            'amount' => (int) round(((float) $payment->amount) * 100),
            'currency' => $payment->currency,
            'orderStatus' => 2,
        ]);

        $response->assertOk();
        $this->assertSame('success', $response->getContent());
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_palmpesa_initiates_mobile_money_and_handles_callback(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.user_id' => '25',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill([
            'credentials' => [],
            'is_active' => true,
            'supports_payments' => true,
        ])->save();

        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated. Processing will continue asynchronously.',
                'order_id' => 'PALMPESA17682869972044',
            ], 200),
            '*/api/order-status' => Http::response([
                'reference' => '0927530628',
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'message' => 'Order fetch successful',
                'data' => [[
                    'order_id' => 'PALMPESA17682869972044',
                    'amount' => '10000',
                    'payment_status' => 'COMPLETED',
                    'transid' => '805613901007',
                    'channel' => 'AIRTELMONEY',
                    'msisdn' => '255711987654',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.payment.provider', 'palmpesa')
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->assertSame('PALMPESA17682869972044', $payment->external_reference);

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'order_id' => 'PALMPESA17682869972044',
            'payment_status' => 'COMPLETED',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_palmpesa_webhook_completes_enrollment_already_marked_paid(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill(['credentials' => [], 'is_active' => true, 'supports_payments' => true])->save();
        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated.',
                'order_id' => 'PALMPESA-STUCK-001',
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        // Payment settled but account creation failed (e.g. mail outage rolled it back).
        $payment->forceFill([
            'status' => PlatformPayment::STATUS_PAID,
            'paid_at' => now(),
            'processed_at' => now(),
        ])->save();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'order_id' => 'PALMPESA-STUCK-001',
            'payment_status' => 'COMPLETED',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', ['reference' => $reference, 'status' => 'completed']);
    }

    public function test_palmpesa_webhook_survives_undecryptable_provider_credentials(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill(['credentials' => [], 'is_active' => true, 'supports_payments' => true])->save();
        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated.',
                'order_id' => 'PALMPESA-CORRUPT-001',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA-CORRUPT-001',
                    'amount' => '10000',
                    'payment_status' => 'COMPLETED',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        // Simulate a rotated APP_KEY / plaintext row: the stored value is no longer decryptable.
        DB::table('payment_providers')
            ->where('slug', PaymentProvider::SLUG_PALMPESA)
            ->update(['credentials' => 'not-a-valid-encrypted-payload']);

        $this->assertSame([], PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail()->credentials);

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'order_id' => 'PALMPESA-CORRUPT-001',
            'payment_status' => 'COMPLETED',
        ])->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_palmpesa_webhook_resolves_payment_by_tx_ref(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill(['credentials' => [], 'is_active' => true, 'supports_payments' => true])->save();
        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated.',
                'order_id' => 'PALMPESA-TXREF-001',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA-TXREF-001',
                    'amount' => '10000',
                    'payment_status' => 'COMPLETED',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => $payment->reference,
                'status' => 'successful',
                'amount' => 10000,
                'currency' => 'TZS',
            ],
        ])->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_palmpesa_callback_fulfills_product_purchase_order(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill(['credentials' => [], 'is_active' => true, 'supports_payments' => true])->save();
        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated.',
                'order_id' => 'PALMPESA-SHOP-001',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA-SHOP-001',
                    'amount' => '100000',
                    'payment_status' => 'COMPLETED',
                    'currency' => 'TZS',
                ]],
            ], 200),
        ]);

        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner');
        $headers = $this->authHeaders($owner);

        $brand = Brand::query()->create(['name' => 'Shop Brand', 'slug' => 'shop-brand', 'is_active' => true]);
        $category = DeviceCategory::query()->create(['name' => 'Shop Category']);
        $device = Device::query()->create([
            'name' => 'Office Router',
            'slug' => 'office-router',
            'sku' => 'SKU-SHOP-1',
            'device_category_id' => $category->id,
            'brand_id' => $brand->id,
            'price' => 100000,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/cart/items', ['device_id' => $device->id, 'quantity' => 1])
            ->assertOk();

        $checkout = $this->withHeaders($headers)
            ->postJson('/api/v1/cart/checkout', ['fulfillment_method' => 'delivery', 'phone' => '0712345678'])
            ->assertCreated()
            ->assertJsonPath('data.payment.purpose', 'device_purchase');

        $orderId = (int) $checkout->json('data.order.id');
        $payment = PlatformPayment::query()->where('order_id', $orderId)->firstOrFail();

        $this->assertSame('PALMPESA-SHOP-001', $payment->external_reference);

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'order_id' => 'PALMPESA-SHOP-001',
            'payment_status' => 'COMPLETED',
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);
        $this->assertSame(4, $device->fresh()->stock_quantity);
    }

    public function test_palmpesa_callback_completes_enrollment_after_expiry_within_grace(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill(['credentials' => [], 'is_active' => true, 'supports_payments' => true])->save();
        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated.',
                'order_id' => 'PALMPESA-LATE-001',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA-LATE-001',
                    'amount' => '10000',
                    'payment_status' => 'COMPLETED',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        Enrollment::query()->where('reference', $reference)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'order_id' => 'PALMPESA-LATE-001',
            'payment_status' => 'COMPLETED',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('signup_intents', ['reference' => $reference, 'status' => 'completed']);
    }

    public function test_enrollment_payment_status_poll_reconciles_palmpesa_and_completes(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill(['credentials' => [], 'is_active' => true, 'supports_payments' => true])->save();
        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated.',
                'order_id' => 'PALMPESA-POLL-001',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA-POLL-001',
                    'amount' => '10000',
                    'payment_status' => 'COMPLETED',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'completed')
            ->assertJsonPath('data.account_created', true);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_palmpesa_reconciles_still_pending_after_four_minutes_via_order_status(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill([
            'credentials' => [],
            'is_active' => true,
            'supports_payments' => true,
        ])->save();

        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated. Processing will continue asynchronously.',
                'order_id' => 'PALMPESA17683440586334',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA17683440586334',
                    'amount' => '10000',
                    'payment_status' => 'COMPLETED',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $this->assertSame('pending', $payment->status);

        $payment->forceFill(['initiated_at' => now()->subMinutes(5)])->save();

        $this->artisan('payments:reconcile-palmpesa')->assertSuccessful();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_palmpesa_reconcile_marks_failed_when_order_status_failed(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill([
            'credentials' => [],
            'is_active' => true,
            'supports_payments' => true,
        ])->save();

        app(PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated. Processing will continue asynchronously.',
                'order_id' => 'PALMPESA-FAILED-001',
            ], 200),
            '*/api/order-status' => Http::response([
                'resultcode' => '000',
                'data' => [[
                    'order_id' => 'PALMPESA-FAILED-001',
                    'amount' => '10000',
                    'payment_status' => 'FAILED',
                ]],
            ], 200),
        ]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        $payment->forceFill(['initiated_at' => now()->subMinutes(5)])->save();

        $this->artisan('payments:reconcile-palmpesa')->assertSuccessful();

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_superadmin_can_dry_run_provider_payment_without_persisting(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $palmpesa = PaymentProvider::query()->where('slug', PaymentProvider::SLUG_PALMPESA)->firstOrFail();
        $palmpesa->forceFill([
            'credentials' => [],
            'is_active' => true,
            'supports_payments' => true,
        ])->save();

        Http::fake([
            '*/api/palmpesa/initiate' => Http::response([
                'message' => 'Payment initiated. Processing will continue asynchronously.',
                'order_id' => 'PALMPESA-TEST-ORDER-99',
            ], 200),
        ]);

        $before = PlatformPayment::query()->count();

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/test/payments/palmpesa', [
                'phone' => '0711987654',
                'amount' => 500,
                'currency' => 'TZS',
                'name' => 'Test Customer',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'palmpesa')
            ->assertJsonPath('data.persisted', false)
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.provider_reference', 'PALMPESA-TEST-ORDER-99')
            ->assertJsonPath('data.provider_response.order_id', 'PALMPESA-TEST-ORDER-99');

        $this->assertSame($before, PlatformPayment::query()->count());
    }

    public function test_non_superadmin_cannot_dry_run_provider_payment(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/test/payments/palmpesa', [
                'phone' => '0711987654',
                'amount' => 500,
            ])
            ->assertForbidden();
    }
}
