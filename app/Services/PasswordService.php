<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function update(User $user, string $currentPassword, string $newPassword): void
    {
        if (! $user->canAuthenticate()) {
            abort(403, 'This account is not allowed to change password.');
        }

        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $this->auditLogger->log('password_changed', $user, $user->current_company_id, User::class, $user->id);
    }

    public function sendResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        $user = User::query()->where('email', $email)->first();
        if ($user) {
            $this->auditLogger->log('password_reset_requested', $user, entityType: User::class, entityId: $user->id);
        }

        return $status;
    }

    public function reset(array $credentials): string
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'status' => $user->status === 'pending' ? 'pending' : $user->status,
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));

            $this->auditLogger->log('password_reset_completed', $user, entityType: User::class, entityId: $user->id);
        });

        return $status;
    }
}
