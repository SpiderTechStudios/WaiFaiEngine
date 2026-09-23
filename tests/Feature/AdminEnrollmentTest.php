<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Services\PlatformPaymentService;
use Tests\TestCase;

class AdminEnrollmentTest extends TestCase
{
    public function test_superadmin_can_list_stuck_enrollments_with_payment_status(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);

        $completedReference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $completedEnrollment = Enrollment::query()->where('reference', $completedReference)->firstOrFail();
        app(PlatformPaymentService::class)->markPaid(
            PlatformPayment::query()->findOrFail($completedEnrollment->payments()->latest('id')->value('id'))
        );

        $pendingReference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload([
            'email' => 'other@example.com',
            'phone' => '0700999888',
            'domain_name' => 'other-cafe',
        ]))->assertCreated()->json('data.enrollment_reference');

        $response = $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/enrollments')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.summary.pending_payment', 1)
            ->assertJsonPath('data.summary.total', 1);

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame($pendingReference, $items[0]['reference']);
        $this->assertSame('pending', $items[0]['payment_status']);
        $this->assertSame('pending_payment', $items[0]['status']);
        $this->assertSame('other@example.com', $items[0]['email']);
        $this->assertNotNull($items[0]['latest_payment']);
        $this->assertSame('pending', $items[0]['latest_payment']['status']);
    }

    public function test_superadmin_can_filter_stuck_enrollments_by_status_and_search(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);

        $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated();

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/enrollments?search=jane@example.com')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/enrollments?search=nobody@example.com')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/enrollments?status=expired')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_superadmin_can_view_enrollment_payment_history(): void
    {
        $admin = $this->createUser(['is_superadmin' => true]);

        $reference = $this->postJson('/api/v1/auth/register', $this->enrollmentPayload())
            ->assertCreated()
            ->json('data.enrollment_reference');

        $payment = Enrollment::query()->where('reference', $reference)->firstOrFail()
            ->payments()->latest('id')->firstOrFail();

        app(PlatformPaymentService::class)->markFailed($payment, 'declined');

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/enrollments/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'payment_failed')
            ->assertJsonPath('data.payment_status', 'failed')
            ->assertJsonPath('data.payments.0.status', 'failed')
            ->assertJsonPath('data.payments.0.failure_reason', 'declined');
    }

    public function test_non_superadmin_cannot_list_enrollments(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/superadmin/enrollments')
            ->assertForbidden();
    }
}
