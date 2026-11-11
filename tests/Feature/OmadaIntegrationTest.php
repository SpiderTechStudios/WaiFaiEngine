<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\OmadaClientSession;
use App\Models\OmadaIntegration;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmadaIntegrationTest extends TestCase
{
    private User $owner;

    private Company $company;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'UTC'));

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', [
            'subdomain' => 'omada-cafe',
            'payment_method' => 'both',
        ]);
        $this->headers = $this->authHeaders($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fakeOmada(): void
    {
        Http::fake([
            'https://omada.test:8043/api/info' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => [
                    'controllerVer' => '6.2.10.17',
                    'apiVer' => '3',
                    'omadacId' => 'abc123',
                ],
            ]),
            'https://omada.test:8043/openapi/authorize/token*' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => ['accessToken' => 'AT-test-token', 'expiresIn' => 7200],
            ]),
            'https://omada.test:8043/openapi/v1/abc123/sites?*' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => [
                    'data' => [
                        ['siteId' => 'site-1', 'name' => 'HQ', 'timeZone' => 'Africa/Dar_es_Salaam'],
                    ],
                    'totalRows' => 1,
                ],
            ]),
            'https://omada.test:8043/openapi/v1/abc123/sites/site-1/devices*' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => [
                    'data' => [
                        [
                            'type' => 'ap',
                            'mac' => 'AA-BB-CC-DD-EE-FF',
                            'name' => 'Lobby AP',
                            'model' => 'EAP225',
                            'ip' => '10.0.0.5',
                            'firmwareVersion' => '5.0.9',
                            'statusCategory' => 1,
                        ],
                    ],
                ],
            ]),
            'https://omada.test:8043/openapi/v1/abc123/sites/site-1/wlans*' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => [
                    'data' => [
                        ['id' => 'wlan-1', 'name' => 'WaiFai Guest', 'enable' => true, 'portalEnable' => true],
                    ],
                ],
            ]),
            'https://omada.test:8043/abc123/api/v2/hotspot/login' => Http::response(
                ['errorCode' => 0, 'msg' => 'Hotspot log in successfully.', 'result' => ['token' => 'csrf-1']],
                200,
                ['Set-Cookie' => 'TPOMADA_SESSIONID=session-1; Path=/']
            ),
            'https://omada.test:8043/abc123/api/v2/hotspot/extPortal/auth' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => [],
            ]),
            'https://omada.test:8043/openapi/v1/abc123/sites/site-1/clients/*' => Http::response([
                'errorCode' => 0,
                'msg' => 'Success.',
                'result' => [],
            ]),
        ]);
    }

    public function test_creates_omada_integration_and_encrypts_credentials(): void
    {
        $this->fakeOmada();

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/omada/integrations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Omada HQ')
            ->assertJsonPath('data.deployment_type', 'software')
            ->assertJsonPath('data.connection_status', 'connected')
            ->assertJsonPath('data.credentials_set.client_secret', true)
            ->assertJsonPath('data.credentials_set.operator_password', true);

        $integration = OmadaIntegration::query()->findOrFail($response->json('data.id'));

        $this->assertSame($this->company->id, $integration->company_id);
        $this->assertSame('6.2.10.17', $integration->controller_version);

        $router = NetworkDevice::query()->findOrFail($integration->network_device_id);
        $this->assertSame(NetworkDevice::GATEWAY_OMADA, $router->gateway_type);
        $this->assertSame('omada.test', $router->api_host);

        $raw = (string) DB::table('omada_integrations')->where('id', $integration->id)->value('credentials');
        $this->assertStringNotContainsString('client-secret', $raw);
        $this->assertStringNotContainsString('operator-secret', $raw);
        $this->assertStringNotContainsString('operator-secret', $response->getContent());
    }

    public function test_pre_save_connection_test_returns_sites(): void
    {
        $this->fakeOmada();

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/omada/integrations/test', [
                'deployment_type' => 'software',
                'controller_url' => 'https://omada.test:8043',
                'controller_id' => 'abc123',
                'client_id' => 'client-id',
                'client_secret' => 'client-secret',
                'verify_tls' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.reachable', true)
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.controller_version', '6.2.10.17')
            ->assertJsonPath('data.sites_count', 1)
            ->assertJsonPath('data.sites.0.site_id', 'site-1');
    }

    public function test_discovers_sites_access_points_and_ssids(): void
    {
        $this->fakeOmada();

        $integration = $this->integration();

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/omada/integrations/'.$integration->id.'/sites')
            ->assertOk()
            ->assertJsonPath('data.items.0.site_id', 'site-1')
            ->assertJsonPath('data.items.0.site_name', 'HQ');

        $this->withHeaders($this->headers)
            ->putJson('/api/v1/omada/integrations/'.$integration->id.'/site', ['site_id' => 'site-1'])
            ->assertOk()
            ->assertJsonPath('data.site_id', 'site-1');

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/omada/integrations/'.$integration->id.'/access-points')
            ->assertOk()
            ->assertJsonPath('data.items.0.device_name', 'Lobby AP')
            ->assertJsonPath('data.items.0.online_status', 'online');

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/omada/integrations/'.$integration->id.'/ssids')
            ->assertOk()
            ->assertJsonPath('data.items.0.ssid_name', 'WaiFai Guest')
            ->assertJsonPath('data.items.0.portal_enabled', true);
    }

    public function test_invalid_credentials_are_reported_without_leaking_secrets(): void
    {
        Http::fake([
            'https://omada.test:8043/api/info' => Http::response(['errorCode' => 0, 'result' => ['omadacId' => 'abc123', 'controllerVer' => '6.2.10.17']]),
            'https://omada.test:8043/openapi/authorize/token*' => Http::response([
                'errorCode' => -44106,
                'msg' => 'The Client Id Or Client Secret is Invalid.',
            ], 200),
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/omada/integrations/test', [
                'controller_url' => 'https://omada.test:8043',
                'client_id' => 'bad-id',
                'client_secret' => 'super-secret-value',
            ])
            ->assertOk()
            ->assertJsonPath('data.authenticated', false)
            ->assertJsonPath('message', 'The Omada Client ID or Client Secret is invalid.')
            ->assertDontSee('super-secret-value');
    }

    public function test_router_api_accepts_omada_gateway_type(): void
    {
        $this->fakeOmada();

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/routers', [
                'gateway_type' => 'omada',
                'name' => 'Omada Router',
                'api_host' => 'omada.test',
                'api_port' => 8043,
                'api_verify_tls' => true,
                'connection_mode' => 'public',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gateway_type', 'omada')
            ->assertJsonPath('data.api_port', 8043);
    }

    public function test_other_company_cannot_access_omada_integration(): void
    {
        $this->fakeOmada();

        $integration = $this->integration();

        $intruder = $this->createUser();
        $this->createCompanyFor($intruder, 'owner', ['subdomain' => 'intruder']);
        $intruderHeaders = $this->authHeaders($intruder);

        $this->withHeaders($intruderHeaders)
            ->getJson('/api/v1/omada/integrations/'.$integration->id)
            ->assertNotFound();

        $this->withHeaders($intruderHeaders)
            ->postJson('/api/v1/omada/integrations/'.$integration->id.'/test')
            ->assertNotFound();
    }

    public function test_portal_entry_validates_omada_parameters(): void
    {
        $this->fakeOmada();

        $integration = $this->integration();

        $this->getJson('/api/omada/portal/'.$integration->portal_key.'?clientMac=AA:BB:CC:DD:EE:FF&site=HQ&ssidName=WaiFai%20Guest&t='.time())
            ->assertOk()
            ->assertJsonPath('data.client.mac', 'AA-BB-CC-DD-EE-FF')
            ->assertJsonPath('data.authorization_status', 'pending')
            ->assertJsonPath('data.portal_url', url('/connect?subdomain=omada-cafe'));

        $this->getJson('/api/omada/portal/'.$integration->portal_key.'?clientMac=AA:BB:CC:DD:EE:FF&site=OtherSite')
            ->assertUnprocessable();
    }

    public function test_authorizes_client_only_with_a_paid_payment(): void
    {
        $this->fakeOmada();

        $integration = $this->integration();
        $plan = $this->plan();
        $pending = $this->payment('pending', $plan);
        $paid = $this->payment('paid', $plan);

        $entry = $this->getJson('/api/omada/portal/'.$integration->portal_key.'?clientMac=AA:BB:CC:DD:EE:FF&site=HQ&ssidName=WaiFai%20Guest')
            ->assertOk();

        $sessionId = $entry->json('data.client_session_id');

        $this->postJson('/api/omada/portal/'.$integration->portal_key.'/authorize', [
            'client_session_id' => $sessionId,
            'payment_transaction_id' => $pending->id,
        ])->assertUnprocessable();

        $this->postJson('/api/omada/portal/'.$integration->portal_key.'/authorize', [
            'client_session_id' => $sessionId,
            'payment_transaction_id' => $paid->id,
        ])->assertOk()
            ->assertJsonPath('data.authorization_status', 'authorized');

        $session = OmadaClientSession::query()->findOrFail($sessionId);
        $this->assertNotNull($session->expires_at);
        $this->assertSame($paid->id, $session->payment_transaction_id);
    }

    public function test_authorizes_client_with_an_active_access_grant(): void
    {
        $this->fakeOmada();

        $integration = $this->integration();
        $plan = $this->plan();

        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Omada Guest',
            'phone' => '0754000001',
            'status' => 'active',
        ]);

        $grant = AccessGrant::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'source' => 'voucher',
            'starts_at' => now(),
            'expires_at' => now()->addHours(2),
            'status' => 'active',
        ]);

        $entry = $this->getJson('/api/omada/portal/'.$integration->portal_key.'?clientMac=11:22:33:44:55:66&site=HQ')->assertOk();

        $this->postJson('/api/omada/portal/'.$integration->portal_key.'/authorize', [
            'client_session_id' => $entry->json('data.client_session_id'),
            'access_grant_id' => $grant->id,
        ])->assertOk()->assertJsonPath('data.authorization_status', 'authorized');
    }

    public function test_sync_command_expires_finished_sessions(): void
    {
        $this->fakeOmada();

        $integration = $this->integration();

        $session = OmadaClientSession::query()->create([
            'company_id' => $this->company->id,
            'omada_integration_id' => $integration->id,
            'site_id' => 'site-1',
            'client_mac' => 'AA-BB-CC-DD-EE-FF',
            'authorization_status' => OmadaClientSession::STATUS_AUTHORIZED,
            'authorized_at' => now()->subHours(3),
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('omada:sync', ['--integration' => $integration->id])->assertExitCode(0);

        $session->refresh();
        $this->assertSame(OmadaClientSession::STATUS_EXPIRED, $session->authorization_status);
    }

    private function integration(): OmadaIntegration
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/omada/integrations', $this->payload())
            ->assertCreated();

        return OmadaIntegration::query()->findOrFail($response->json('data.id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Omada HQ',
            'deployment_type' => 'software',
            'controller_url' => 'https://omada.test:8043',
            'controller_port' => 8043,
            'controller_id' => 'abc123',
            'connection_method' => 'public',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'operator_username' => 'operator',
            'operator_password' => 'operator-secret',
            'verify_tls' => true,
        ];
    }

    private function plan(): InternetPlan
    {
        return InternetPlan::query()->create([
            'company_id' => $this->company->id,
            'name' => '1 Hour',
            'slug' => 'one-hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'price' => 1000,
            'status' => 'active',
        ]);
    }

    private function payment(string $status, InternetPlan $plan): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.strtoupper($status).'-'.uniqid(),
            'amount' => 1000,
            'currency' => 'TZS',
            'payment_method' => 'mobile_money',
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }
}
