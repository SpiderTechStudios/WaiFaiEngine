<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PlatformUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private PlatformUserService $platformUserService) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()->latest()->paginate($request->integer('per_page', 15));

        return $this->success([
            'items' => UserResource::collection($users->items())->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ], 'Users retrieved');
    }

    public function show(User $user): JsonResponse
    {
        return $this->success((new UserResource($user))->resolve(), 'User retrieved');
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return $this->error([], 'You cannot suspend your own account.', 422);
        }

        $user = $this->platformUserService->suspend($request->user(), $user);

        return $this->success((new UserResource($user))->resolve(), 'User suspended');
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $user = $this->platformUserService->activate($request->user(), $user);

        return $this->success((new UserResource($user))->resolve(), 'User activated');
    }
}
