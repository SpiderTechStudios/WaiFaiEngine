<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (($data['otp_channel'] ?? null) === 'sms' && blank($user->phone)) {
            throw ValidationException::withMessages([
                'otp_channel' => ['Add a mobile number to your profile before choosing SMS.'],
            ]);
        }

        if (array_key_exists('otp_channel', $data)) {
            $user->forceFill(['otp_channel' => $data['otp_channel']])->save();
        }

        return $this->success((new UserResource($user->fresh()))->resolve(), 'Profile updated');
    }
}
