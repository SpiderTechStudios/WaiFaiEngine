<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalOtpNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WithdrawalManualApprovalTest extends TestCase
{
    public function test_admin_can_manually_approve_without_calling_payout_provider(): void
    {
        Notification::fake();
        Http::fake();

        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $wallet = $this->fundWallet($company, 1000);

        $id = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals', [
                'amount' => 200,
                'destination_phone' => '0754000000',
                'destination_name' => 'Asha Mohamed',
            ])->assertCreated()->json('data.id');

        $code = null;
        Notification::assertSentTo(
            $owner,
            WithdrawalOtpNotification::class,
            function (WithdrawalOtpNotification $notification) use (&$code) {
                $code = $notification->code;

                return true;
            },
        );

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals/'.$id.'/confirm', ['otp' => $code])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/withdrawals/'.$id.'/approve-manual', [
                'note' => 'Paid cash at the office',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.requires_approval', false);

        Http::assertNothingSent();

        $withdrawal = Withdrawal::query()->findOrFail($id);
        $this->assertSame(Withdrawal::STATUS_COMPLETED, $withdrawal->status);
        $this->assertSame($admin->id, $withdrawal->approved_by);
        $this->assertNotNull($withdrawal->approved_at);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertNull($withdrawal->external_reference);
        $this->assertSame('manual', data_get($withdrawal->metadata, 'payout.method'));
        $this->assertSame('Paid cash at the office', data_get($withdrawal->metadata, 'payout.note'));

        // Wallet was already debited at request time; manual approval must not
        // debit or refund it again.
        $this->assertEquals(800, (float) $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', $id)
            ->where('type', 'debit')
            ->count());
        $this->assertSame(0, WalletTransaction::query()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', $id)
            ->where('type', 'credit')
            ->count());
    }

    public function test_manual_approval_debits_wallet_when_hold_is_missing(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $wallet = $this->fundWallet($company, 1000);

        $withdrawal = $this->createPendingApprovalWithdrawal($company, $wallet, $owner, 400);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/withdrawals/'.$withdrawal->id.'/approve-manual')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals(600, (float) $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'type' => 'debit',
            'amount' => 400,
        ]);

        // A second approval is rejected and never debits again.
        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/withdrawals/'.$withdrawal->id.'/approve-manual')
            ->assertStatus(422)
            ->assertJsonPath('data.withdrawal.0', 'Only OTP-confirmed withdrawals awaiting approval can be approved manually.');

        $this->assertEquals(600, (float) $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'debit')
            ->count());
    }

    public function test_manual_approval_requires_otp_confirmation(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $wallet = $this->fundWallet($company, 1000);

        $id = $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/withdrawals', [
                'amount' => 200,
                'destination_phone' => '0754000000',
            ])->assertCreated()->json('data.id');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/withdrawals/'.$id.'/approve-manual')
            ->assertStatus(422)
            ->assertJsonPath('data.withdrawal.0', 'Only OTP-confirmed withdrawals awaiting approval can be approved manually.');

        $this->assertSame(Withdrawal::STATUS_PENDING, Withdrawal::query()->findOrFail($id)->status);
        $this->assertEquals(800, (float) $wallet->fresh()->balance);
    }

    public function test_non_admin_cannot_manually_approve(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $wallet = $this->fundWallet($company, 1000);

        $withdrawal = $this->createPendingApprovalWithdrawal($company, $wallet, $owner, 400);

        $this->withHeaders($this->authHeaders($owner))
            ->postJson('/api/v1/admin/withdrawals/'.$withdrawal->id.'/approve-manual')
            ->assertForbidden();
    }

    private function createPendingApprovalWithdrawal(Company $company, Wallet $wallet, User $owner, float $amount): Withdrawal
    {
        return Withdrawal::query()->create([
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'requested_by' => $owner->id,
            'provider' => 'mpesa',
            'destination_phone' => '255754000000',
            'destination_name' => 'Asha Mohamed',
            'reference' => 'WD-MANUAL'.strtoupper(str()->random(6)),
            'amount' => $amount,
            'platform_fee' => 0,
            'provider_fee' => 0,
            'fee_total' => 0,
            'net_amount' => $amount,
            'currency' => 'TZS',
            'status' => Withdrawal::STATUS_PENDING_APPROVAL,
            'confirmed_at' => now(),
            'requested_at' => now(),
        ]);
    }

    private function fundWallet(Company $company, float $balance): Wallet
    {
        return Wallet::query()->updateOrCreate(
            ['company_id' => $company->id, 'currency' => 'TZS'],
            ['balance' => $balance, 'status' => 'active'],
        );
    }
}
