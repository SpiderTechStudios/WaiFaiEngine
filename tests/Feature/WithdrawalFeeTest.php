<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalOtpNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WithdrawalFeeTest extends TestCase
{
    public function test_admin_can_configure_payout_fees_and_preview_returns_breakdown(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->withHeaders($this->authHeaders($superadmin))
            ->putJson('/api/v1/superadmin/payment-providers/afriqorepay/payout-fees', [
                'platform_percent' => 2,
                'platform_fixed' => 1,
                'provider_percent' => 1,
                'provider_fixed' => 0.5,
            ])->assertOk()
            ->assertJsonPath('data.payout_fees.platform_percent', 2)
            ->assertJsonPath('data.payout_fees.platform_fixed', 1)
            ->assertJsonPath('data.payout_fees.provider_percent', 1)
            ->assertJsonPath('data.payout_fees.provider_fixed', 0.5);

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $this->fundWallet($company, 1000);

        $response = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals/preview', [
                'amount' => 200,
                'payment_provider_slug' => 'afriqorepay',
                'destination_phone' => '0754000000',
            ])->assertOk()
            ->assertJsonPath('data.network', 'mpesa')
            ->assertJsonPath('data.payment_provider.slug', 'afriqorepay')
            ->assertJsonPath('data.sufficient_balance', true)
            ->json('data');

        $this->assertEqualsWithDelta(5, (float) $response['platform_fee'], 0.001);
        $this->assertEqualsWithDelta(2.5, (float) $response['provider_fee'], 0.001);
        $this->assertEqualsWithDelta(7.5, (float) $response['fee_total'], 0.001);
        $this->assertEqualsWithDelta(192.5, (float) $response['net_amount'], 0.001);
    }

    public function test_withdrawal_request_sends_otp_and_confirm_initiates_payout(): void
    {
        Notification::fake();

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $wallet = $this->fundWallet($company, 1000);

        $id = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals', [
                'amount' => 200,
                'destination_phone' => '0754000000',
                'destination_name' => 'Asha Mohamed',
            ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requires_confirmation', true)
            ->assertJsonPath('data.network', 'mpesa')
            ->assertJsonPath('data.net_amount', '200.00')
            ->json('data.id');

        $this->assertEquals(800, (float) $wallet->fresh()->balance);

        $code = null;

        Notification::assertSentTo(
            $owner,
            WithdrawalOtpNotification::class,
            function (WithdrawalOtpNotification $notification) use (&$code) {
                $code = $notification->code;

                return true;
            },
        );

        $this->assertNotNull($code);

        $wrongCode = $code === '000000' ? '111111' : '000000';

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals/'.$id.'/confirm', ['otp' => $wrongCode])
            ->assertStatus(422)
            ->assertJsonPath('data.otp.0', 'The confirmation code is incorrect.');

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals/'.$id.'/confirm', ['otp' => $code])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.requires_confirmation', false);

        $withdrawal = Withdrawal::query()->findOrFail($id);
        $this->assertNotNull($withdrawal->confirmed_at);
        $this->assertStringStartsWith('STUB-PAYOUT-', (string) $withdrawal->external_reference);
        $this->assertEquals(800, (float) $wallet->fresh()->balance);
    }

    public function test_payout_accounts_detect_network_and_manage_default(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $firstId = $this->withHeaders($headers)
            ->postJson('/api/v1/withdrawals/payout-accounts', [
                'name' => 'Asha Mohamed',
                'phone' => '0754000000',
            ])->assertCreated()
            ->assertJsonPath('data.network', 'mpesa')
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $secondId = $this->withHeaders($headers)
            ->postJson('/api/v1/withdrawals/payout-accounts', [
                'name' => 'Juma Ally',
                'phone' => '0684000000',
            ])->assertCreated()
            ->assertJsonPath('data.network', 'airtelmoney')
            ->assertJsonPath('data.is_default', false)
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/withdrawals/payout-accounts/'.$secondId.'/default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->withHeaders($headers)->getJson('/api/v1/withdrawals/payout-accounts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $secondId)
            ->assertJsonPath('data.1.id', $firstId);
    }

    public function test_payout_webhook_marks_withdrawal_completed(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $this->fundWallet($company, 1000);

        $id = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals', [
                'amount' => 200,
                'destination_phone' => '0754000000',
                'destination_name' => 'Asha Mohamed',
            ])->assertCreated()->json('data.id');

        $withdrawal = Withdrawal::query()->findOrFail($id);
        $withdrawal->forceFill([
            'status' => Withdrawal::STATUS_PROCESSING,
            'confirmed_at' => now(),
            'external_reference' => 'STUB-PAYOUT-1',
        ])->save();

        $this->postJson('/api/v1/webhooks/payouts/stub', [
            'reference' => $withdrawal->reference,
            'provider_reference' => 'STUB-PAYOUT-1',
            'status' => 'successful',
            'amount' => 200,
            'currency' => 'TZS',
        ], [
            'X-Platform-Payment-Secret' => 'stub-webhook-secret',
        ])->assertOk()
            ->assertJsonPath('data.withdrawal_status', 'completed');

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_COMPLETED, $withdrawal->status);
        $this->assertNotNull($withdrawal->processed_at);
    }

    public function test_user_can_choose_otp_channel_and_email_is_default(): void
    {
        $user = $this->createUser(['phone' => '0754000000']);
        $headers = $this->authHeaders($user);

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.otp_channel', 'email');

        $this->withHeaders($headers)->patchJson('/api/v1/auth/profile', [
            'otp_channel' => 'sms',
        ])->assertOk()
            ->assertJsonPath('data.otp_channel', 'sms');

        $this->assertSame('sms', $user->fresh()->otp_channel);

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.otp_channel', 'sms');
    }

    public function test_sms_otp_channel_requires_a_phone_number(): void
    {
        $user = $this->createUser(['phone' => null]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson('/api/v1/auth/profile', ['otp_channel' => 'sms'])
            ->assertStatus(422)
            ->assertJsonPath('data.otp_channel.0', 'Add a mobile number to your profile before choosing SMS.');
    }

    public function test_withdrawal_otp_is_sent_by_sms_when_user_prefers_sms(): void
    {
        Notification::fake();

        Http::fake([
            'messaging-service.co.tz/*' => Http::response([
                'messages' => [[
                    'to' => '255754000000',
                    'status' => ['groupName' => 'PENDING', 'name' => 'PENDING_ENROUTE'],
                    'messageId' => 'msg-otp-1',
                ]],
            ], 200),
        ]);

        $owner = $this->createUser(['phone' => '0754000000']);
        $company = $this->createCompanyFor($owner);
        $this->fundWallet($company, 1000);

        $this->withHeaders($this->authHeaders($owner))
            ->patchJson('/api/v1/auth/profile', ['otp_channel' => 'sms'])
            ->assertOk();

        $id = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals', [
                'amount' => 200,
                'destination_phone' => '0754000000',
                'destination_name' => 'Asha Mohamed',
            ])->assertCreated()->json('data.id');

        $withdrawal = Withdrawal::query()->findOrFail($id);

        $this->assertSame('sms', data_get($withdrawal->metadata, 'otp_channel'));
        Notification::assertNotSentTo($owner, WithdrawalOtpNotification::class);
        $this->assertDatabaseHas('sms_messages', [
            'provider' => 'nextsms',
            'recipient' => '255754000000',
            'provider_message_id' => 'msg-otp-1',
        ]);
        $this->assertSame(1, SmsMessage::query()->count());
    }

    private function fundWallet(Company $company, float $balance): Wallet
    {
        return Wallet::query()->updateOrCreate(
            ['company_id' => $company->id, 'currency' => 'TZS'],
            ['balance' => $balance, 'status' => 'active'],
        );
    }
}
