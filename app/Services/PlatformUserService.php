<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminRegisteredNotification;
use Illuminate\Support\Str;

class PlatformUserService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function registerAdmin(User $actor, array $data): User
    {
        $isSuperadmin = $data['account_type'] === 'superadmin';
        $password = Str::password(16);

        $user = User::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $password,
            'status' => 'active',
            'email_verified_at' => now(),
            'is_superadmin' => $isSuperadmin,
            'is_admin' => ! $isSuperadmin,
            'created_by' => $actor->id,
        ]);

        $user->notify(new AdminRegisteredNotification($actor, $data['account_type'], $password));

        $this->auditLogger->log(
            $isSuperadmin ? 'superadmin_registered' : 'admin_registered',
            $actor,
            entityType: User::class,
            entityId: $user->id,
            newValues: [
                'email' => $user->email,
                'account_type' => $data['account_type'],
            ],
        );

        return $user;
    }

    public function suspend(User $actor, User $user): User
    {
        $user->forceFill(['status' => 'suspended'])->save();
        $user->tokens()->delete();

        $this->auditLogger->log('user_suspended', $actor, entityType: User::class, entityId: $user->id);

        return $user;
    }

    public function activate(User $actor, User $user): User
    {
        $user->forceFill(['status' => 'active'])->save();

        $this->auditLogger->log('user_activated', $actor, entityType: User::class, entityId: $user->id);

        return $user;
    }
}
