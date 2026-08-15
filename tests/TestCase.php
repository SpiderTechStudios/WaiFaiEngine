<?php

namespace Tests;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->app['auth']->forgetGuards();

        parent::tearDown();
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (isset($this->app['auth'])) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function createUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    protected function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->token($user)];
    }

    protected function roleId(string $slug): int
    {
        return (int) Role::query()->whereNull('company_id')->where('slug', $slug)->value('id');
    }

    protected function createCompanyFor(User $user, string $role = 'owner', array $companyOverrides = []): Company
    {
        $company = Company::factory()->create([
            'created_by' => $user->id,
            ...$companyOverrides,
        ]);

        $this->attach($user, $company, $role);

        if (! $user->current_company_id) {
            $user->forceFill(['current_company_id' => $company->id])->save();
        }

        return $company;
    }

    protected function attach(User $user, Company $company, string $role = 'operator', string $status = 'active'): UserCompany
    {
        return UserCompany::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $this->roleId($role),
            'status' => $status,
            'joined_at' => now(),
        ]);
    }
}
