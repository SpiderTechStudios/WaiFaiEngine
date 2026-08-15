<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function index(RegisterRequest $request): JsonResponse
    {
        return app(SessionController::class)->register($request);
    }
}
