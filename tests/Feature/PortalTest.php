<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use Tests\TestCase;

class PortalTest extends TestCase
{
    public function test_portal_bootstrap_returns_branding_and_packages(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', [
            'subdomain' => 'demo-cafe',
            'primary_color' => '#112233',
            'captive_portal_welcome_message' => 'Welcome',
            'payment_method' => 'both',
        ]);

        $headers = $this->authHeaders($owner);
        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/portal/demo-cafe')
            ->assertOk()
            ->assertJsonPath('data.company.subdomain', 'demo-cafe')
            ->assertJsonPath('data.company.primary_color', '#112233')
            ->assertJsonPath('data.company.captive_portal_welcome_message', 'Welcome')
            ->assertJsonPath('data.packages.0.id', $packageId)
            ->assertJsonMissingPath('data.company.ruijie_password');
    }

    public function test_portal_returns_404_for_unknown_or_suspended_subdomain(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'active-cafe']);

        $this->getJson('/api/v1/portal/missing-cafe')->assertNotFound();

        $suspended = $this->createCompanyFor($owner, 'owner', [
            'subdomain' => 'closed-cafe',
            'status' => 'suspended',
        ]);

        $this->getJson('/api/v1/portal/'.$suspended->subdomain)->assertNotFound();
    }

    public function test_portal_payment_is_pending_and_uses_plan_price(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'pay-cafe']);
        $headers = $this->authHeaders($owner);

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => 'Daily',
            'duration' => 1,
            'duration_unit' => 'DAYS',
            'price' => 2500,
        ])->assertCreated()->json('data.id');

        $payment = $this->postJson('/api/v1/portal/pay-cafe/payments', [
            'internet_plan_id' => $packageId,
            'customer_name' => 'Portal Guest',
            'customer_phone' => '0711222333',
            'payment_method' => 'mpesa',
            'amount' => 1,
            'status' => 'paid',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '2500.00')
            ->assertJsonPath('data.provider', 'stub')
            ->assertJsonPath('data.next_action', 'poll_payment')
            ->json('data');

        $this->assertNotEmpty($payment['provider_reference'] ?? null);

        $this->getJson('/api/v1/portal/pay-cafe/payments/'.$payment['id'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.package.name', 'Daily');
    }

    public function test_portal_payment_triggers_default_provider_ussd(): void
    {
        config([
            'services.palmpesa.api_token' => 'test-palmpesa-token',
            'services.palmpesa.user_id' => '25',
            'services.palmpesa.base_url' => 'https://palmpesa.drmlelwa.co.tz',
        ]);

        $palmpesa = \App\Models\PaymentProvider::query()
            ->where('slug', \App\Models\PaymentProvider::SLUG_PALMPESA)
            ->firstOrFail();
        app(\App\Services\PaymentProviderService::class)->setDefaultForPayments($palmpesa->fresh());

        \Illuminate\Support\Facades\Http::fake([
            '*/api/palmpesa/initiate' => \Illuminate\Support\Facades\Http::response([
                'message' => 'Payment initiated. Processing will continue asynchronously.',
                'order_id' => 'PALMPESA-PORTAL-001',
            ], 200),
            '*/api/order-status' => \Illuminate\Support\Facades\Http::response([
                'resultcode' => '000',
                'result' => 'SUCCESS',
                'data' => [[
                    'order_id' => 'PALMPESA-PORTAL-001',
                    'amount' => '1000',
                    'payment_status' => 'COMPLETED',
                    'currency' => 'TZS',
                ]],
            ], 200),
        ]);

        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'ussd-cafe']);
        $headers = $this->authHeaders($owner);

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $payment = $this->postJson('/api/v1/portal/ussd-cafe/payments', [
            'internet_plan_id' => $packageId,
            'customer_phone' => '0711987654',
            'captive_session' => str_repeat('a', 64),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'palmpesa')
            ->assertJsonPath('data.provider_reference', 'PALMPESA-PORTAL-001')
            ->json('data');

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/palmpesa/initiate')
                && ($request['phone'] ?? null) === '0711987654'
                && (int) ($request['amount'] ?? 0) === 1000;
        });

        $platform = \App\Models\PlatformPayment::query()
            ->where('reference', $payment['platform_payment_reference'])
            ->firstOrFail();

        $this->postJson('/api/v1/webhooks/payments/palmpesa', [
            'order_id' => 'PALMPESA-PORTAL-001',
            'payment_status' => 'COMPLETED',
            'amount' => 1000,
            'currency' => 'TZS',
        ])->assertOk();

        $this->getJson('/api/v1/portal/ussd-cafe/payments/'.$payment['id'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.next_action', 'start_session');
    }

    public function test_portal_voucher_redeem_by_code(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'voucher-cafe']);
        $headers = $this->authHeaders($owner);

        $router = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Portal Router',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data');

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $code = $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
            'router_id' => $router['id'],
            'package_id' => $packageId,
            'quantity' => 1,
            'custom_code' => '123456',
        ])->assertCreated()->json('data.items.0.code');

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Voucher Guest',
            'customer_phone' => '0700111222',
        ])->assertCreated()
            ->assertJsonPath('data.access_grant.status', 'active')
            ->assertJsonPath('data.package.name', '1 Hour');
    }

    public function test_portal_session_after_paid_payment(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'session-cafe']);
        $headers = $this->authHeaders($owner);

        $router = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Session Router',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data');

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $paymentId = $this->postJson('/api/v1/portal/session-cafe/payments', [
            'internet_plan_id' => $packageId,
            'customer_name' => 'Session Guest',
            'customer_phone' => '0700333444',
        ])->assertCreated()->json('data.id');

        PaymentTransaction::query()->whereKey($paymentId)->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->postJson('/api/v1/portal/session-cafe/sessions', [
            'payment_transaction_id' => $paymentId,
            'mac_address' => 'aa-bb-cc-dd-ee-ff',
            'router_id' => $router['id'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.mac_address', 'AA:BB:CC:DD:EE:FF');
    }

    public function test_portal_rejects_session_for_pending_payment(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'pending-cafe']);
        $headers = $this->authHeaders($owner);

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $paymentId = $this->postJson('/api/v1/portal/pending-cafe/payments', [
            'internet_plan_id' => $packageId,
            'customer_name' => 'Pending Guest',
            'customer_phone' => '0700555666',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/portal/pending-cafe/sessions', [
            'payment_transaction_id' => $paymentId,
            'mac_address' => 'aa-bb-cc-dd-ee-ff',
        ])->assertStatus(422)
            ->assertJsonPath('data.payment_transaction_id.0', 'Only paid payments can start a hotspot session.');
    }

    public function test_portal_restore_by_phone(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'restore-cafe']);
        $headers = $this->authHeaders($owner);

        $router = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Restore Router',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data');

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $grantId = $this->postJson('/api/v1/portal/restore-cafe/vouchers/redeem', [
            'code' => $this->withHeaders($headers)->postJson('/api/v1/vouchers', [
                'router_id' => $router['id'],
                'package_id' => $packageId,
                'quantity' => 1,
                'custom_code' => '654321',
            ])->assertCreated()->json('data.items.0.code'),
            'customer_name' => 'Restore Guest',
            'customer_phone' => '0700777888',
        ])->assertCreated()->json('data.access_grant.id');

        $this->postJson('/api/v1/portal/restore-cafe/restore', [
            'customer_phone' => '0700777888',
        ])->assertOk()
            ->assertJsonPath('data.access_grant.id', $grantId)
            ->assertJsonPath('data.customer.phone', '0700777888');
    }

    public function test_portal_payment_is_scoped_to_tenant(): void
    {
        $owner = $this->createUser();
        $companyA = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'tenant-a']);
        $companyB = $this->createCompanyFor($owner, 'owner', ['subdomain' => 'tenant-b']);
        $headers = $this->authHeaders($owner);

        $packageId = $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => '1 Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
        ])->assertCreated()->json('data.id');

        $paymentId = $this->postJson('/api/v1/portal/tenant-a/payments', [
            'internet_plan_id' => $packageId,
            'customer_name' => 'Tenant A Guest',
            'customer_phone' => '0700999000',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/portal/tenant-b/payments/'.$paymentId)->assertNotFound();
    }

    public function test_portal_rejects_invalid_voucher_code(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'invalid-voucher']);

        $this->postJson('/api/v1/portal/invalid-voucher/vouchers/redeem', [
            'code' => 'NOPE99',
            'customer_name' => 'Guest',
            'customer_phone' => '0700111222',
        ])->assertStatus(422)
            ->assertJsonPath('data.code.0', 'Invalid voucher code.');
    }
}
