<?php

namespace App\Services;

use App\Http\Resources\AuthSessionResource;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function login(string $email, string $password, ?string $deviceName = 'auth'): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->auditLogger->log(
                'failed_login',
                $user,
                entityType: User::class,
                entityId: $user?->id,
                newValues: ['email' => $email],
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => ['This account is not allowed to sign in.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName ?: 'auth')->plainTextToken;

        $this->auditLogger->log('login', $user, $user->current_company_id, User::class, $user->id);

        return $this->sessionPayload($user, $token);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
        $this->auditLogger->log('logout', $user, $user->current_company_id, User::class, $user->id);
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
        $this->auditLogger->log('logout_all', $user, $user->current_company_id, User::class, $user->id);
    }

    public function switchCompany(User $user, Company $company): User
    {
        $membership = $user->membershipFor($company);

        if (! $membership || $membership->status !== 'active') {
            abort(403, 'You do not belong to this company.');
        }

        if ($company->status !== 'active' && ! $user->is_superadmin) {
            abort(403, 'This company is not active.');
        }

        $user->forceFill(['current_company_id' => $company->id])->save();

        $this->auditLogger->log(
            'company_switched',
            $user,
            $company->id,
            Company::class,
            $company->id,
        );

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionPayload(User $user, ?string $token = null): array
    {
        $user->load([
            'currentCompany',
            'memberships.company',
            'memberships.role.permissions',
        ]);

        $currentMembership = $user->current_company_id
            ? $user->memberships->firstWhere('company_id', $user->current_company_id)
            : null;

        $payload = [
            'user' => $user,
            'companies' => $user->memberships
                ->filter(fn (UserCompany $membership) => $membership->status !== 'removed')
                ->values(),
            'current_company' => $user->currentCompany,
            'membership' => $currentMembership,
            'permissions' => $currentMembership?->role?->permissions->pluck('slug')->values() ?? collect(),
        ];

        if ($token !== null) {
            $payload['token'] = $token;
        }

        return $payload;
    }

    public function resource(User $user, ?string $token = null): AuthSessionResource
    {
        return new AuthSessionResource($this->sessionPayload($user, $token));
    }
}
