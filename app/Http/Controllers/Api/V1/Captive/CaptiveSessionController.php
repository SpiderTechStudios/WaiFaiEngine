<?php

namespace App\Http\Controllers\Api\V1\Captive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Captive\AuthorizeCaptiveSessionRequest;
use App\Services\CaptiveSessionService;
use Illuminate\Http\JsonResponse;

class CaptiveSessionController extends Controller
{
    public function __construct(private CaptiveSessionService $captiveSessionService) {}

    public function show(string $token): JsonResponse
    {
        $session = $this->captiveSessionService->findByToken($token);

        if (! $session) {
            return $this->error([], 'Captive session not found.', 404);
        }

        if ($session->company?->status !== 'active') {
            return $this->error([], 'Captive session not found.', 404);
        }

        return $this->success(
            $this->captiveSessionService->toPublicArray($session),
            'Captive session retrieved',
        );
    }

    public function authorize(AuthorizeCaptiveSessionRequest $request, string $token): JsonResponse
    {
        $session = $this->captiveSessionService->findByToken($token);

        if (! $session || $session->company?->status !== 'active') {
            return $this->error([], 'Captive session not found.', 404);
        }

        $session = $this->captiveSessionService->authenticate($session, $request->validated());

        return $this->success([
            ...$this->captiveSessionService->toPublicArray($session),
            'gateway_auth_url' => $this->captiveSessionService->gatewayAuthRedirectUrl($session),
        ], 'Captive session authorized');
    }
}
