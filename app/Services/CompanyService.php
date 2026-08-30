<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data) {
            $company = Company::query()->create([
                'created_by' => $user->id,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'subdomain' => $this->uniqueSubdomain($data['subdomain'] ?? $data['name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'status' => 'active',
                'setup_type' => $data['setup_type'] ?? null,
                'installation_paid_at' => $data['installation_paid_at'] ?? null,
                'subscription_status' => $data['subscription_status'] ?? 'active',
                'activated_at' => $data['activated_at'] ?? now(),
                'subscription_period_ends_at' => $data['subscription_period_ends_at'] ?? null,
                'captive_portal_welcome_message' => 'Welcome to WiFi. Choose a package or enter a voucher code.',
            ]);

            $ownerRole = Role::query()->whereNull('company_id')->where('slug', 'owner')->firstOrFail();

            UserCompany::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_id' => $ownerRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $company->wallets()->create([
                'currency' => 'TZS',
                'balance' => 0,
                'status' => 'active',
            ]);

            if (! $user->current_company_id) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }

            $this->auditLogger->log('company_created', $user, $company->id, Company::class, $company->id);

            return $company->load('memberships.role');
        });
    }

    public function update(Company $company, array $data, User $actor): Company
    {
        $company->fill($data)->save();

        $this->auditLogger->log(
            'company_updated',
            $actor,
            $company->id,
            Company::class,
            $company->id,
            newValues: $data,
        );

        return $company;
    }

    public function suspend(Company $company, User $actor): Company
    {
        $company->forceFill(['status' => 'suspended'])->save();
        $this->auditLogger->log('company_suspended', $actor, $company->id, Company::class, $company->id);

        return $company;
    }

    public function activate(Company $company, User $actor): Company
    {
        $company->forceFill(['status' => 'active'])->save();
        $this->auditLogger->log('company_activated', $actor, $company->id, Company::class, $company->id);

        return $company;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;

        while (Company::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function uniqueSubdomain(string $value): string
    {
        $base = Str::slug($value) ?: 'wifi';
        $subdomain = $base;
        $i = 1;

        while (Company::query()->where('subdomain', $subdomain)->exists()) {
            $subdomain = $base.'-'.$i++;
        }

        return $subdomain;
    }
}
