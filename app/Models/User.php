<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable, HasApiTokens;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'status',
        'is_superadmin',
        'is_admin',
        'last_login_at',
        'created_by',
        'current_company_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
            'is_admin' => 'boolean',
            'created_by' => 'integer',
            'current_company_id' => 'integer',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    public function isAccountActive(): bool
    {
        return in_array($this->status, ['active', 'pending'], true);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_superadmin || $this->is_admin;
    }

    public function canAuthenticate(): bool
    {
        return $this->isAccountActive();
    }

    public function membershipFor(Company|int $company): ?UserCompany
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $this->memberships()
            ->with('role.permissions')
            ->where('company_id', $companyId)
            ->first();
    }

    public function hasCompanyPermission(string $permission, Company|int $company): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        $membership = $this->membershipFor($company);

        if (! $membership || $membership->status !== 'active') {
            return false;
        }

        $membership->role?->loadMissing('permissions');

        return $membership->role?->permissions->contains('slug', $permission) ?? false;
    }

    public function canAssignRole(string $roleSlug, Company|int $company): bool
    {
        if ($this->is_superadmin) {
            return in_array($roleSlug, Permissions::assignableBy('owner'), true);
        }

        $membership = $this->membershipFor($company);
        $actorSlug = $membership?->role?->slug ?? '';

        return in_array($roleSlug, Permissions::assignableBy($actorSlug), true);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function createdCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(UserCompany::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'requested_by');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies')
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
