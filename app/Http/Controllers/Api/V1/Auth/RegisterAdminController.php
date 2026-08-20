<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAdminRequest;
use App\Http\Resources\UserResource;
use App\Services\PlatformUserService;
use Illuminate\Http\JsonResponse;

class RegisterAdminController extends Controller
{
    public function __construct(private PlatformUserService $platformUserService) {}

    public function store(RegisterAdminRequest $request): JsonResponse
    {
        $user = $this->platformUserService->registerAdmin(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            (new UserResource($user))->resolve(),
            'Administrator registered successfully',
            201,
        );
    }
}
