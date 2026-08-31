<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchCompanyRequest;
use App\Http\Resources\AuthSessionResource;
use App\Models\Company;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('device_name'),
        );

        return $this->session($payload, 'Logged in successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->session(
            $this->authService->sessionPayload($request->user()),
            'Authenticated user',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success([], 'Logged out successfully');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return $this->success([], 'All sessions have been revoked');
    }

    public function switchCompany(SwitchCompanyRequest $request): JsonResponse
    {
        $company = Company::query()->findOrFail($request->integer('company_id'));
        $user = $this->authService->switchCompany($request->user(), $company);

        return $this->session(
            $this->authService->sessionPayload($user),
            'Company switched successfully',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function session(array $payload, string $message, int $code = 200): JsonResponse
    {
        return $this->success(
            (new AuthSessionResource($payload))->resolve(),
            $message,
            $code,
        );
    }


    public function defaultPage()
    {
        return $this->defaultErrorPage();
    }
}
