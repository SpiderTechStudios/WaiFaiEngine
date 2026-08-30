<?php

namespace Tests\Feature;

use App\Models\InstallationRequest;
use App\Models\PlatformPayment;
use App\Services\PlatformPaymentService;
use Tests\TestCase;

class InstallationRequestTest extends TestCase
{
    public function test_owner_can_create_installation_only_request_with_server_pricing(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->postJson('/api/v1/installation-requests', [
            'service_type' => 'installation_only',
            'quantity' => 2,
            'unit_price' => 1,
            'total_amount' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.service_type', 'installation_only')
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.unit_price', '100000.00')
            ->assertJsonPath('data.total_amount', '200000.00')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.fulfillment_status', 'requested')
            ->assertJsonPath('data.progress.0.key', 'requested');

        $this->assertDatabaseCount('installation_request_items', 2);
    }

    public function test_router_and_installation_pricing(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))->postJson('/api/v1/installation-requests', [
            'service_type' => 'router_and_installation',
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.unit_price', '150000.00')
            ->assertJsonPath('data.total_amount', '300000.00');
    }

    public function test_customer_cannot_advance_fulfillment(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $id = $this->withHeaders($headers)->postJson('/api/v1/installation-requests', [
            'service_type' => 'installation_only',
            'quantity' => 1,
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/installation-requests/'.$id.'/fulfillment', [
                'fulfillment_status' => 'processing',
            ])->assertForbidden();
    }

    public function test_tenant_isolation_on_installation_requests(): void
    {
        $ownerA = $this->createUser();
        $this->createCompanyFor($ownerA);
        $id = $this->withHeaders($this->authHeaders($ownerA))->postJson('/api/v1/installation-requests', [
            'service_type' => 'installation_only',
            'quantity' => 1,
        ])->assertCreated()->json('data.id');

        $ownerB = $this->createUser();
        $this->createCompanyFor($ownerB);

        $this->withHeaders($this->authHeaders($ownerB))
            ->getJson('/api/v1/installation-requests/'.$id)
            ->assertNotFound();
    }

    public function test_installation_payment_and_admin_fulfillment_flow(): void
    {
        $owner = $this->createUser(['is_admin' => true]);
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $requestId = $this->withHeaders($headers)->postJson('/api/v1/installation-requests', [
            'service_type' => 'installation_only',
            'quantity' => 2,
        ])->assertCreated()->json('data.id');

        $paymentId = $this->withHeaders($headers)->postJson('/api/v1/installation-requests/'.$requestId.'/payments')
            ->assertCreated()
            ->assertJsonPath('data.amount', '200000.00')
            ->json('data.payment_id');

        app(PlatformPaymentService::class)->markPaid(
            PlatformPayment::query()->findOrFail($paymentId)
        );

        $this->assertDatabaseHas('installation_requests', [
            'id' => $requestId,
            'payment_status' => 'paid',
        ]);

        $this->withHeaders($headers)->patchJson('/api/v1/admin/installation-requests/'.$requestId.'/fulfillment', [
            'fulfillment_status' => 'processing',
            'note' => 'Preparing kit',
        ])->assertOk()
            ->assertJsonPath('data.fulfillment_status', 'processing');

        $this->withHeaders($headers)->postJson('/api/v1/admin/installation-requests/'.$requestId.'/updates', [
            'visibility' => 'customer',
            'body' => 'Technician scheduled for tomorrow',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/admin/installation-requests/'.$requestId.'/updates', [
            'visibility' => 'internal',
            'body' => 'Call warehouse',
        ])->assertCreated();

        $customerView = $this->withHeaders($headers)
            ->getJson('/api/v1/installation-requests/'.$requestId)
            ->assertOk();

        $updateBodies = collect($customerView->json('data.updates'))->pluck('body');
        $this->assertTrue($updateBodies->contains('Technician scheduled for tomorrow'));
        $this->assertFalse($updateBodies->contains('Call warehouse'));

        $adminView = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/installation-requests/'.$requestId)
            ->assertOk();

        $adminBodies = collect($adminView->json('data.updates'))->pluck('body');
        $this->assertTrue($adminBodies->contains('Call warehouse'));
    }

    public function test_cannot_advance_fulfillment_before_payment(): void
    {
        $admin = $this->createUser(['is_admin' => true]);
        $this->createCompanyFor($admin);
        $headers = $this->authHeaders($admin);

        $requestId = $this->withHeaders($headers)->postJson('/api/v1/installation-requests', [
            'service_type' => 'router_and_installation',
            'quantity' => 1,
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)->patchJson('/api/v1/admin/installation-requests/'.$requestId.'/fulfillment', [
            'fulfillment_status' => 'processing',
        ])->assertStatus(422);
    }

    public function test_expired_subscription_blocks_dashboard_but_allows_billing(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $company->forceFill([
            'subscription_status' => 'expired',
            'subscription_period_ends_at' => now()->subDays(30),
        ])->save();

        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->getJson('/api/v1/dashboard')->assertForbidden();

        $this->withHeaders($headers)->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.is_access_allowed', false);

        $paymentId = $this->withHeaders($headers)->postJson('/api/v1/billing/subscription/payments')
            ->assertCreated()
            ->assertJsonPath('data.amount', '10000.00')
            ->json('data.payment_id');

        app(PlatformPaymentService::class)->markPaid(
            PlatformPayment::query()->findOrFail($paymentId)
        );

        $this->withHeaders($headers)->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_access_allowed', true);

        $this->withHeaders($headers)->getJson('/api/v1/dashboard')->assertOk();
    }
}
