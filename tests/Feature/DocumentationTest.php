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
        $this->assertContains('/signup/intents', $paths);
        $this->assertContains('/auth/admin/register', $paths);
        $this->assertContains('/auth/me', $paths);
        $this->assertContains('/auth/forgot-password', $paths);
        $this->assertContains('/dashboard', $paths);
        $this->assertContains('/routers', $paths);
        $this->assertContains('/packages', $paths);
        $this->assertContains('/settings', $paths);
        $this->assertContains('/portal/{subdomain}', $paths);
        $this->assertNotContains('/companies', $paths);
    }
}
