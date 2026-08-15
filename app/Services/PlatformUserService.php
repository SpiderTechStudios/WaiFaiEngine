<?php

namespace App\Services;

use App\Models\User;

class PlatformUserService
{
    public function __construct(private AuditLogger $auditLogger) {}

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
