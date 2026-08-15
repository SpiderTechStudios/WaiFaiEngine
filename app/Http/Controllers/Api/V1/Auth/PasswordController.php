<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    public function __construct(private PasswordService $passwordService) {}

    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $this->passwordService->update(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return $this->success([], 'Password updated successfully');
    }

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordService->sendResetLink($request->string('email')->toString());

        return $this->success([], 'If the account exists, a password reset link has been sent.');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->passwordService->reset($request->only('email', 'password', 'password_confirmation', 'token'));

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(['email' => [__($status)]], __($status), 422);
        }

        return $this->success([], 'Password has been reset successfully');
    }
}
