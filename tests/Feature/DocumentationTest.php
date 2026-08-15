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
        $this->assertContains('/companies', $paths);
        $this->assertContains('/companies/{company}/staff', $paths);
        $this->assertContains('/superadmin/users', $paths);
    }
}
