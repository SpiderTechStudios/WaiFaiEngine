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
        $membership = UserCompany::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $this->roleId($role),
            'status' => $status,
            'joined_at' => now(),
        ]);

        if (! $user->current_company_id) {
            $user->forceFill(['current_company_id' => $company->id])->save();
        }

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function signupIntentPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'ABC Internet',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '0700123456',
            'payment_phone' => '0711987654',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Dar es Salaam, Tanzania',
            'portal_subdomain' => 'abc-internet',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{intent_id: string, payment_id: int, token: string, response: \Illuminate\Testing\TestResponse}
     */
    protected function completePaidSignup(array $overrides = []): array
    {
        $payload = $this->signupIntentPayload($overrides);
        $subscription = (int) config('platform.subscription_monthly');

        $intentId = $this->postJson('/api/v1/signup/intents', $payload)
            ->assertCreated()
            ->json('data.intent_id');

        $paymentId = $this->postJson('/api/v1/signup/intents/'.$intentId.'/payments', [
            'payment_method' => 'mpesa',
            'amount' => $subscription,
        ])->assertCreated()->json('data.payment_id');

        app(\App\Services\PlatformPaymentService::class)->markPaid(
            \App\Models\PlatformPayment::query()->findOrFail($paymentId)
        );

        $response = $this->postJson('/api/v1/signup/intents/'.$intentId.'/complete')
            ->assertCreated();

        return [
            'intent_id' => $intentId,
            'payment_id' => $paymentId,
            'token' => $response->json('data.token'),
            'response' => $response,
        ];
    }
}
