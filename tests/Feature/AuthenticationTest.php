<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_user_can_register(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'business_name' => 'ABC Internet',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '0700123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.user.first_name', 'Jane')
            ->assertJsonPath('data.user.last_name', 'Doe')
            ->assertJsonPath('data.user.phone', '0700123456')
            ->assertJsonPath('data.current_company.name', 'ABC Internet')
            ->assertJsonPath('data.membership.role.slug', 'owner')
            ->assertJsonStructure(['data' => ['token', 'user', 'companies', 'current_company']]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '0700123456',
        ]);
        $this->assertDatabaseHas('companies', ['name' => 'ABC Internet']);
        Notification::assertSentTo(User::query()->where('email', 'jane@example.com')->first(), VerifyEmailNotification::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->createUser(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'ABC Internet',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '0700123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'ABC Internet',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '0700123456',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);
    }

    public function test_user_can_login(): void
    {
        $user = $this->createUser(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->createUser(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $this->createUser(['email' => 'jane@example.com', 'status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createUser(['email' => 'jane@example.com', 'status' => 'inactive']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_me_returns_session(): void
    {
        $user = $this->createUser();
        $company = $this->createCompanyFor($user);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.current_company.id', $company->id);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser();
        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_all_revokes_tokens(): void
    {
        $user = $this->createUser();
        $first = $this->token($user);
        $second = $this->token($user);

        $this->withToken($first)->postJson('/api/v1/auth/logout-all')->assertOk();
        $this->withToken($second)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_password_can_be_changed(): void
    {
        $user = $this->createUser();

        $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->createUser();

        $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'nope',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertStatus(422);
    }

    public function test_forgot_and_reset_password(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'reset-password',
        ])->assertOk();
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422);
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ])->assertStatus(422);
    }

    public function test_email_can_be_verified(): void
    {
        $user = $this->createUser([
            'email_verified_at' => null,
            'status' => 'pending',
        ]);

        $url = URL::temporarySignedRoute('api.v1.auth.email.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_legacy_register_route_still_works(): void
    {
        $this->postJson('/api/v1/register', [
            'business_name' => 'Legacy ISP',
            'first_name' => 'Legacy',
            'last_name' => 'User',
            'email' => 'legacy@example.com',
            'phone' => '0700999888',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.current_company.name', 'Legacy ISP');
    }
}
