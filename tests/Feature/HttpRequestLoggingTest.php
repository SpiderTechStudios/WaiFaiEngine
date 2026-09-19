<?php

namespace Tests\Feature;

use Tests\TestCase;

class HttpRequestLoggingTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/testing-http-requests.log');

        config([
            'logging.http_request_logging' => true,
            'logging.channels.http_requests.path' => $this->logPath,
        ]);

        @unlink($this->logPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);

        parent::tearDown();
    }

    public function test_it_logs_every_request_including_404s_and_adds_request_id(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner, 'owner', ['subdomain' => 'juku']);

        $ok = $this->get('/connect?subdomain=juku');
        $ok->assertOk();
        $this->assertNotEmpty($ok->headers->get('X-Request-ID'));

        $this->get('/this-route-does-not-exist-'.uniqid())->assertNotFound();

        $log = (string) file_get_contents($this->logPath);

        $this->assertStringContainsString('GET /connect', $log);
        $this->assertStringContainsString('status     : 200', $log);
        $this->assertStringContainsString('this-route-does-not-exist', $log);
        $this->assertStringContainsString('status     : 404', $log);
        $this->assertStringContainsString('request_id :', $log);
    }

    public function test_it_redacts_sensitive_input(): void
    {
        $this->post('/this-route-does-not-exist', [
            'email' => 'guest@example.com',
            'password' => 'super-secret-value',
            'password_confirmation' => 'super-secret-value',
            'api_token' => 'tok_123',
        ])->assertNotFound();

        $log = (string) file_get_contents($this->logPath);

        $this->assertStringNotContainsString('super-secret-value', $log);
        $this->assertStringNotContainsString('tok_123', $log);
        $this->assertStringContainsString('guest@example.com', $log);
        $this->assertStringContainsString('"password":"***"', $log);
    }
}
