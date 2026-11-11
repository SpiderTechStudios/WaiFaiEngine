<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\SelectOmadaAccessPointsRequest;
use App\Http\Requests\Operations\SelectOmadaSiteRequest;
use App\Http\Requests\Operations\SelectOmadaSsidRequest;
use App\Http\Requests\Operations\StoreOmadaIntegrationRequest;
use App\Http\Requests\Operations\TestOmadaConnectionRequest;
use App\Http\Resources\OmadaAccessPointResource;
use App\Http\Resources\OmadaClientSessionResource;
use App\Http\Resources\OmadaIntegrationResource;
use App\Http\Resources\OmadaSiteResource;
use App\Http\Resources\OmadaSsidResource;
use App\Models\OmadaAccessPoint;
use App\Models\OmadaClientSession;
use App\Models\OmadaIntegration;
use App\Models\OmadaSite;
use App\Models\OmadaSsid;
use App\Services\Omada\OmadaControllerClient;
use App\Services\Omada\OmadaEndpointValidator;
use App\Services\Omada\OmadaException;
use App\Services\Omada\OmadaIntegrationService;
use App\Services\Omada\OmadaSessionService;
use App\Services\RouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OmadaIntegrationController extends Controller
{
    public function __construct(
        private readonly RouterService $routerService,
        private readonly OmadaIntegrationService $omada,
        private readonly OmadaEndpointValidator $endpointValidator,
        private readonly OmadaSessionService $sessions,
    ) {}

    public function index(): JsonResponse
    {
        $company = $this->currentCompany();

        $items = OmadaIntegration::query()
            ->where('company_id', $company->id)
            ->withCount([
                'sites',
                'accessPoints',
                'ssids',
                'clientSessions as active_clients_count' => fn ($query) => $query->where('authorization_status', OmadaClientSession::STATUS_AUTHORIZED)->where('expires_at', '>', now()),
            ])
            ->orderByDesc('id')
            ->get();

        return $this->success(['items' => OmadaIntegrationResource::collection($items)->resolve()]);
    }

    public function store(StoreOmadaIntegrationRequest $request): JsonResponse
    {
        $company = $this->currentCompany();
        $user = $request->user();
        $data = $request->validated();

        [$host, $port] = $this->hostAndPort($data);

        [$router, $integration] = DB::transaction(function () use ($company, $user, $data, $host, $port) {
            $router = $this->routerService->create($company, [
                'gateway_type' => 'omada',
                'name' => $data['name'],
                'branch_id' => $data['branch_id'] ?? null,
                'api_host' => $host,
                'api_port' => $port,
                'api_username' => $data['client_id'] ?? null,
                'api_verify_tls' => $data['verify_tls'] ?? true,
                'connection_mode' => ($data['connection_method'] ?? 'public') === 'vpn' ? 'vpn' : 'public',
                'omada_controller_id' => $data['controller_id'] ?? null,
                'status' => $data['status'] ?? 'active',
            ], $user);

            $integration = $this->omada->createForRouter($company, $router, $data, $user);

            return [$router, $integration];
        });

        return $this->success(
            (new OmadaIntegrationResource($integration->loadCount(['sites', 'accessPoints', 'ssids'])))->resolve(),
            'Omada integration created.',
            201
        );
    }

    public function show(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        return $this->success(
            (new OmadaIntegrationResource($integration->loadCount(['sites', 'accessPoints', 'ssids', 'clientSessions'])))->resolve()
        );
    }

    public function update(StoreOmadaIntegrationRequest $request, OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $data = $request->validated();
        $updated = $this->omada->update($integration, $data, $request->user());

        if ($updated->networkDevice && isset($data['name'])) {
            $updated->networkDevice->update(['name' => $data['name']]);
        }

        return $this->success((new OmadaIntegrationResource($updated))->resolve(), 'Omada integration updated.');
    }

    public function destroy(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $this->omada->delete($integration, request()->user());

        return $this->success([], 'Omada integration removed.');
    }

    public function test(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $result = $this->omada->test($integration);

        return $this->success($result, (string) ($result['message'] ?? 'Connection test completed.'));
    }

    public function testConnection(TestOmadaConnectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $verifyTls = filter_var($data['verify_tls'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $origin = $this->endpointValidator->validate(
            (string) $data['controller_url'],
            isset($data['controller_port']) ? (int) $data['controller_port'] : null,
            $verifyTls,
            (string) ($data['connection_method'] ?? 'public'),
        );

        $integration = new OmadaIntegration([
            'name' => 'connection-test',
            'deployment_type' => $data['deployment_type'] ?? OmadaIntegration::DEPLOYMENT_SOFTWARE,
            'controller_url' => $origin,
            'controller_host' => (string) parse_url($origin, PHP_URL_HOST),
            'controller_port' => (int) (parse_url($origin, PHP_URL_PORT) ?: config('omada.default_port', 8043)),
            'controller_id' => $data['controller_id'] ?? null,
            'connection_method' => $data['connection_method'] ?? OmadaIntegration::CONNECTION_PUBLIC,
            'verify_tls' => $verifyTls,
            'credentials' => [
                'client_id' => (string) $data['client_id'],
                'client_secret' => (string) $data['client_secret'],
            ],
        ]);

        $started = microtime(true);

        try {
            $client = new OmadaControllerClient($integration);
            $info = $client->info();
            $controllerId = $client->controllerId();
            $sites = $client->paginated('/openapi/v1/'.$controllerId.'/sites');

            return $this->success([
                'reachable' => true,
                'authenticated' => true,
                'controller_id' => $controllerId,
                'controller_version' => $client->controllerVersion(),
                'api_version' => $info['apiVer'] ?? null,
                'sites_count' => count($sites),
                'sites' => array_map(fn (array $site) => [
                    'site_id' => $site['siteId'] ?? null,
                    'name' => $site['name'] ?? null,
                    'timezone' => $site['timeZone'] ?? null,
                ], $sites),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => 'Connected to the Omada controller.',
            ]);
        } catch (OmadaException $exception) {
            return $this->success([
                'reachable' => $exception->httpStatus !== null,
                'authenticated' => false,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => $exception->userMessage(),
            ], $exception->userMessage());
        }
    }

    public function sync(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $test = $this->omada->test($integration);

        if (! ($test['reachable'] ?? false) || ! ($test['authenticated'] ?? false)) {
            return $this->success($test, (string) ($test['message'] ?? 'The Omada controller is not available.'));
        }

        try {
            $sites = $this->omada->syncSites($integration);
            $aps = $integration->site_id ? $this->omada->syncAccessPoints($integration) : [];
            $ssids = $integration->site_id ? $this->omada->syncSsids($integration) : [];
        } catch (OmadaException $exception) {
            return $this->error([], $exception->userMessage(), 502);
        }

        return $this->success([
            'test' => $test,
            'sites' => count($sites),
            'access_points' => count($aps),
            'ssids' => count($ssids),
        ], 'Omada integration synchronized.');
    }

    public function sites(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        try {
            $sites = $this->omada->syncSites($integration);
        } catch (OmadaException $exception) {
            return $this->error([], $exception->userMessage(), 502);
        }

        return $this->success([
            'items' => OmadaSiteResource::collection(collect($sites))->resolve(),
        ]);
    }

    public function accessPoints(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        try {
            $items = $this->omada->syncAccessPoints($integration);
        } catch (OmadaException $exception) {
            return $this->error([], $exception->userMessage(), 502);
        }

        return $this->success([
            'items' => OmadaAccessPointResource::collection(collect($items))->resolve(),
        ]);
    }

    public function ssids(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        try {
            $items = $this->omada->syncSsids($integration);
        } catch (OmadaException $exception) {
            return $this->error([], $exception->userMessage(), 502);
        }

        return $this->success([
            'items' => OmadaSsidResource::collection(collect($items))->resolve(),
        ]);
    }

    public function selectSite(SelectOmadaSiteRequest $request, OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        try {
            $updated = $this->omada->selectSite($integration, (string) $request->validated('site_id'), $request->user());
        } catch (OmadaException $exception) {
            return $this->error([], $exception->userMessage(), 502);
        }

        return $this->success((new OmadaIntegrationResource($updated))->resolve(), 'Omada site selected.');
    }

    public function selectAccessPoints(SelectOmadaAccessPointsRequest $request, OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $this->omada->selectAccessPoints($integration, (array) $request->validated('device_ids'));

        $items = OmadaAccessPoint::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->orderBy('device_name')
            ->get();

        return $this->success([
            'items' => OmadaAccessPointResource::collection($items)->resolve(),
        ], 'Access points updated.');
    }

    public function selectSsid(SelectOmadaSsidRequest $request, OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $this->omada->selectSsid($integration, $request->validated('wlan_id'));

        $items = OmadaSsid::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->orderBy('ssid_name')
            ->get();

        return $this->success([
            'items' => OmadaSsidResource::collection($items)->resolve(),
        ], 'SSID updated.');
    }

    public function sessions(Request $request, OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $status = $request->query('status');

        $items = OmadaClientSession::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->when(filled($status), fn ($query) => $query->where('authorization_status', $status))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $this->success([
            'items' => OmadaClientSessionResource::collection($items)->resolve(),
        ]);
    }

    public function disconnectClient(string $mac, OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $normalized = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $mac));

        if (strlen($normalized) !== 12) {
            return $this->error(['mac' => ['A valid client MAC address is required.']], 'Validation Error', 422);
        }

        $session = OmadaClientSession::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->where('client_mac', 'like', '%'.implode('-', str_split($normalized, 2)).'%')
            ->latest('id')
            ->first();

        if (! $session) {
            $session = OmadaClientSession::query()->create([
                'company_id' => $integration->company_id,
                'omada_integration_id' => $integration->id,
                'network_device_id' => $integration->network_device_id,
                'site_id' => $integration->site_id,
                'client_mac' => implode('-', str_split($normalized, 2)),
                'authorization_status' => OmadaClientSession::STATUS_AUTHORIZED,
            ]);
        }

        $updated = $this->sessions->disconnect($session);

        return $this->success((new OmadaClientSessionResource($updated))->resolve(), 'Client disconnected.');
    }

    public function status(OmadaIntegration $integration): JsonResponse
    {
        $this->assertCompany($integration);

        $sites = OmadaSite::query()->where('omada_integration_id', $integration->id)->get();
        $aps = OmadaAccessPoint::query()->where('omada_integration_id', $integration->id)->get();
        $ssids = OmadaSsid::query()->where('omada_integration_id', $integration->id)->get();
        $sessions = OmadaClientSession::query()->where('omada_integration_id', $integration->id)->get();

        return $this->success([
            'integration_id' => $integration->id,
            'connection_status' => $integration->connection_status,
            'deployment_type' => $integration->deployment_type,
            'controller_version' => $integration->controller_version,
            'sites_count' => $sites->count(),
            'access_points_count' => $aps->count(),
            'online_access_points_count' => $aps->where('online_status', 'online')->count(),
            'ssids_count' => $ssids->count(),
            'configured_ssids' => $ssids->where('selected_for_waifai', true)->pluck('ssid_name')->values()->all(),
            'active_clients_count' => $sessions->where('authorization_status', OmadaClientSession::STATUS_AUTHORIZED)->count(),
            'failed_authorizations_count' => $sessions->where('authorization_status', OmadaClientSession::STATUS_FAILED)->count(),
            'last_synced_at' => $integration->last_synced_at,
        ]);
    }

    private function assertCompany(OmadaIntegration $integration): void
    {
        $company = $this->currentCompany();

        if ((int) $integration->company_id !== (int) $company->id) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: int}
     */
    private function hostAndPort(array $data): array
    {
        $url = (string) ($data['controller_url'] ?? '');
        $url = str_contains($url, '://') ? $url : 'https://'.$url;
        $parts = parse_url($url);

        $host = $parts['host'] ?? null;
        $port = isset($data['controller_port'])
            ? (int) $data['controller_port']
            : (int) ($parts['port'] ?? config('omada.default_port', 8043));

        return [$host, $port];
    }
}
