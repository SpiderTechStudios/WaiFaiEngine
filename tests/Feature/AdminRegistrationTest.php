<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminRegisteredNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminRegistrationTest extends TestCase
{
    public function test_superadmin_can_register_another_superadmin(): void
    {
        Notification::fake();

        $superadmin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);

        $response = $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/auth/admin/register', [
                'first_name' => 'New',
                'last_name' => 'Super',
                'email' => 'new-super@example.com',
                'phone' => '0700111222',
                'account_type' => 'superadmin',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'new-super@example.com')
            ->assertJsonPath('data.is_superadmin', true)
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonPath('data.status', 'active');

        $user = User::query()->where('email', 'new-super@example.com')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'new-super@example.com',
            'is_superadmin' => true,
            'is_admin' => false,
            'created_by' => $superadmin->id,
        ]);

        Notification::assertSentTo($user, AdminRegisteredNotification::class);
    }

    public function test_superadmin_can_register_platform_admin(): void
    {
        Notification::fake();

        $superadmin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/auth/admin/register', [
                'first_name' => 'Platform',
                'last_name' => 'Admin',
                'email' => 'platform-admin@example.com',
                'phone' => '0700333444',
                'account_type' => 'admin',
            ])->assertCreated()
            ->assertJsonPath('data.is_superadmin', false)
            ->assertJsonPath('data.is_admin', true);

        $user = User::query()->where('email', 'platform-admin@example.com')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'platform-admin@example.com',
            'is_superadmin' => false,
            'is_admin' => true,
        ]);

        Notification::assertSentTo($user, AdminRegisteredNotification::class);
    }

    public function test_registered_admin_receives_generated_password_by_email(): void
    {
        $capturedPassword = null;

        Notification::fake();

        $superadmin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/auth/admin/register', [
                'first_name' => 'Email',
                'last_name' => 'Admin',
                'email' => 'email-admin@example.com',
                'phone' => '0700555666',
                'account_type' => 'admin',
            ])->assertCreated();

        $user = User::query()->where('email', 'email-admin@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            AdminRegisteredNotification::class,
            function (AdminRegisteredNotification $notification) use (&$capturedPassword) {
                $capturedPassword = $notification->password;

                return true;
            },
        );

        $this->assertNotNull($capturedPassword);
        $this->assertTrue(Hash::check($capturedPassword, $user->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'email-admin@example.com',
            'password' => $capturedPassword,
        ])->assertOk();
    }

    public function test_non_superadmin_cannot_register_admin(): void
    {
        $user = $this->createUser(['is_superadmin' => false, 'status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/auth/admin/register', [
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'email' => 'blocked@example.com',
                'phone' => '0700777888',
                'account_type' => 'admin',
            ])->assertForbidden();
    }

    public function test_guest_cannot_register_admin(): void
    {
        $this->postJson('/api/v1/auth/admin/register', [
            'first_name' => 'Guest',
            'last_name' => 'User',
            'email' => 'guest@example.com',
            'phone' => '0700999000',
            'account_type' => 'admin',
        ])->assertUnauthorized();
    }
}
