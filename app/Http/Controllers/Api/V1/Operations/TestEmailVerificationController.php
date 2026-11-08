<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TEMPORARY: clears email_verified_at so the verification flow can be retested.
 * Remove this controller and its route after testing.
 */
class TestEmailVerificationController extends Controller
{
    public function unverify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        $user->forceFill(['email_verified_at' => null])->save();

        Log::warning('test_email_verification_cleared', [
            'user_id' => $user->id,
            'email' => $user->email,
            'actor_id' => $request->user()?->id,
        ]);

        return $this->success([
            'id' => $user->id,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'status' => $user->status,
        ], 'Email verification cleared');
    }
}
