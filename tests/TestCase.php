<?php

namespace Tests;

use App\Models\Company;
use App\Models\Enrollment;
use App\Models\PlatformPayment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;
use App\Services\PlatformPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PaymentProviderSeeder::class);
    }

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
    protected function enrollmentPayload(array $overrides = []): array
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
            'domain_name' => 'abc-internet',
        ], $overrides);
    }

    /** @deprecated Use enrollmentPayload() */
    protected function signupIntentPayload(array $overrides = []): array
    {
        $payload = $this->enrollmentPayload($overrides);

        if (array_key_exists('portal_subdomain', $overrides) && ! array_key_exists('domain_name', $overrides)) {
            $payload['domain_name'] = $overrides['portal_subdomain'];
            unset($payload['portal_subdomain']);
        }

        return $payload;
    }

    /**
     * Register → mark enrollment payment paid (trusted path) → login.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{enrollment_reference: string, enrollment_id: string, payment_id: int, token: string, response: \Illuminate\Testing\TestResponse}
     */
    protected function completePaidSignup(array $overrides = []): array
    {
        $payload = $this->enrollmentPayload($overrides);

        if (array_key_exists('portal_subdomain', $overrides) && ! array_key_exists('domain_name', $overrides)) {
            $payload['domain_name'] = $overrides['portal_subdomain'];
            unset($payload['portal_subdomain']);
        }

        $register = $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
        $reference = $register->json('data.enrollment_reference');

        $enrollment = Enrollment::query()->where('reference', $reference)->firstOrFail();
        $payment = $enrollment->payments()->latest('id')->firstOrFail();

        app(PlatformPaymentService::class)->markPaid(
            PlatformPayment::query()->findOrFail($payment->id)
        );

        $this->getJson('/api/v1/auth/enrollments/'.$reference.'/payment-status')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'completed')
            ->assertJsonPath('data.account_created', true);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $payload['email'],
            'password' => $payload['password'],
        ])->assertOk();

        return [
            'enrollment_reference' => $reference,
            'enrollment_id' => $enrollment->id,
            'intent_id' => $enrollment->id,
            'payment_id' => $payment->id,
            'token' => $login->json('data.token'),
            'response' => $login,
        ];
    }
}
