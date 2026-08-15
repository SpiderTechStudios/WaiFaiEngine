<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->error([], 'Invalid verification link.', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            if ($user->status === 'pending') {
                $user->forceFill(['status' => 'active'])->save();
            }
            event(new Verified($user));
        }

        return $this->success([], 'Email verified successfully');
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->success([], 'Email is already verified');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->success([], 'Verification email sent');
    }
}
