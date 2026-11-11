<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\OmadaPortalAuthorizeRequest;
use App\Http\Resources\OmadaClientSessionResource;
use App\Models\OmadaClientSession;
use App\Services\Omada\OmadaException;
use App\Services\Omada\OmadaPortalService;
use App\Services\Omada\OmadaSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OmadaPortalController extends Controller
{
    public function __construct(
        private readonly OmadaPortalService $portal,
        private readonly OmadaSessionService $sessions,
    ) {}

    public function entry(Request $request, string $key): JsonResponse
    {
        $integration = $this->portal->findIntegration($key);
        $params = $this->portal->validateParameters($integration, $request->all());

        $payload = $this->portal->entry($integration, $params);

        return $this->success($payload, 'Omada portal session ready.');
    }

    public function authorize(OmadaPortalAuthorizeRequest $request, string $key): JsonResponse
    {
        $integration = $this->portal->findIntegration($key);
        $data = $request->validated();

        try {
            $session = filled($data['payment_transaction_id'] ?? null)
                ? $this->portal->authorizeByPayment($integration, (int) $data['client_session_id'], (int) $data['payment_transaction_id'])
                : $this->portal->authorizeByGrant($integration, (int) $data['client_session_id'], (int) $data['access_grant_id']);
        } catch (OmadaException $exception) {
            return $this->error([], $exception->userMessage(), 502);
        }

        return $this->success((new OmadaClientSessionResource($session))->resolve(), 'Client authorized.');
    }

    public function disconnect(Request $request, string $key): JsonResponse
    {
        $integration = $this->portal->findIntegration($key);

        $sessionId = $request->integer('client_session_id');
        $clientMac = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', (string) $request->input('client_mac', '')));
        $clientMac = strlen($clientMac) === 12 ? implode('-', str_split($clientMac, 2)) : '';

        $session = OmadaClientSession::query()
            ->where('company_id', $integration->company_id)
            ->where('omada_integration_id', $integration->id)
            ->when($sessionId > 0, fn ($query) => $query->whereKey($sessionId))
            ->when($sessionId <= 0 && $clientMac !== '', fn ($query) => $query->where('client_mac', $clientMac))
            ->latest('id')
            ->first();

        if (! $session) {
            return $this->error([], 'Client session not found.', 404);
        }

        $updated = $this->sessions->disconnect($session);

        return $this->success((new OmadaClientSessionResource($updated))->resolve(), 'Client disconnected.');
    }
}
