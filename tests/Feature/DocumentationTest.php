<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_swagger_ui_is_available(): void
    {
        $this->get('/api/v1/documentation')
            ->assertOk()
            ->assertSee('swagger', false);
    }

    public function test_openapi_json_documents_auth_and_company_routes(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();

        $paths = array_keys($response->json('paths') ?? []);

        $this->assertContains('/auth/login', $paths);
        $this->assertContains('/auth/register', $paths);
        $this->assertContains('/auth/enrollments/{reference}/payment-status', $paths);
        $this->assertContains('/auth/enrollments/{reference}/retry-payment', $paths);
        $this->assertContains('/webhooks/payments/{provider}', $paths);
        $this->assertContains('/webhooks/payouts/{provider}', $paths);
        $this->assertContains('/superadmin/payment-providers/{paymentProvider}', $paths);
        $this->assertContains('/superadmin/payment-providers/{paymentProvider}/default-payments', $paths);
        $this->assertContains('/superadmin/payment-providers/{paymentProvider}/default-payouts', $paths);
        $this->assertContains('/billing/subscription', $paths);
        $this->assertContains('/billing/subscription/payments', $paths);
        $this->assertContains('/billing/subscription/payments/{payment}', $paths);
        $this->assertContains('/installation-requests', $paths);
        $this->assertContains('/installation-requests/{installationRequest}', $paths);
        $this->assertContains('/installation-requests/{installationRequest}/payments', $paths);
        $this->assertContains('/admin/installation-requests', $paths);
        $this->assertContains('/admin/installation-requests/{installationRequest}/fulfillment', $paths);
        $this->assertContains('/admin/installation-requests/{installationRequest}/updates', $paths);
        $this->assertNotContains('/signup/intents', $paths);
        $this->assertNotContains('/signup/intents/{intent}/complete', $paths);
        $this->assertContains('/auth/admin/register', $paths);
        $this->assertContains('/auth/me', $paths);
        $this->assertContains('/auth/forgot-password', $paths);
        $this->assertContains('/dashboard', $paths);
        $this->assertContains('/routers', $paths);
        $this->assertContains('/packages', $paths);
        $this->assertContains('/settings', $paths);
        $this->assertContains('/portal/{subdomain}', $paths);
        $this->assertContains('/captive/sessions/{token}', $paths);
        $this->assertContains('/captive/sessions/{token}/authorize', $paths);
        $this->assertContains('/wifidog/login', $paths);
        $this->assertContains('/wifidog/auth', $paths);
        $this->assertContains('/wifidog/ping', $paths);
        $this->assertNotContains('/companies', $paths);
    }
}
