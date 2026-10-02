<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Tests\TestCase;

class WithdrawalCancelTest extends TestCase
{
    public function test_owner_can_cancel_pending_withdrawal_and_wallet_is_refunded(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $this->fundWallet($company, 1000);

        $id = $this->withHeaders($headers)->postJson('/api/v1/withdrawals', [
            'amount' => 400,
            'provider' => 'mpesa',
            'destination_phone' => '0700111222',
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)->getJson('/api/v1/withdrawals/stats')
            ->assertOk()
            ->assertJsonPath('data.wallet_balance', 600)
            ->assertJsonPath('data.pending_amount', 400);

        $this->withHeaders($headers)->deleteJson('/api/v1/withdrawals/'.$id)
            ->assertOk()
            ->assertJsonPath('message', 'Withdrawal cancelled')
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'cancelled');

        $this->withHeaders($headers)->getJson('/api/v1/withdrawals/stats')
            ->assertOk()
            ->assertJsonPath('data.wallet_balance', 1000)
            ->assertJsonPath('data.pending_amount', 0)
            ->assertJsonPath('data.pending_count', 0)
            ->assertJsonPath('data.cancelled_count', 1)
            ->assertJsonPath('data.cancelled_amount', 400);

        $withdrawal = Withdrawal::query()->findOrFail($id);
        $this->assertSame('cancelled', $withdrawal->status);
        $this->assertNotNull($withdrawal->cancelled_at);
        $this->assertSame($owner->id, $withdrawal->cancelled_by);

        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => Withdrawal::class,
            'reference_id' => $id,
            'type' => 'credit',
            'amount' => 400,
        ]);
    }

    public function test_cancelling_twice_is_rejected_and_refunds_only_once(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $wallet = $this->fundWallet($company, 1000);

        $id = $this->withHeaders($headers)->postJson('/api/v1/withdrawals', [
            'amount' => 400,
            'provider' => 'mpesa',
            'destination_phone' => '0700111222',
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)->deleteJson('/api/v1/withdrawals/'.$id)->assertOk();

        $this->withHeaders($headers)->deleteJson('/api/v1/withdrawals/'.$id)
            ->assertStatus(422)
            ->assertJsonPath('data.withdrawal.0', 'Only pending withdrawals can be cancelled.');

        $this->assertEquals(1000, (float) $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::query()->where('reference_id', $id)->where('type', 'credit')->count());
    }

    public function test_completed_withdrawal_cannot_be_cancelled(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);
        $wallet = $this->fundWallet($company, 1000);

        $id = $this->withHeaders($headers)->postJson('/api/v1/withdrawals', [
            'amount' => 400,
            'provider' => 'mpesa',
            'destination_phone' => '0700111222',
        ])->assertCreated()->json('data.id');

        Withdrawal::query()->whereKey($id)->update(['status' => 'completed']);

        $this->withHeaders($headers)->deleteJson('/api/v1/withdrawals/'.$id)
            ->assertStatus(422)
            ->assertJsonPath('data.withdrawal.0', 'Only pending withdrawals can be cancelled.');

        $this->assertEquals(600, (float) $wallet->fresh()->balance);
    }

    public function test_other_company_withdrawal_returns_not_found(): void
    {
        $ownerA = $this->createUser();
        $companyA = $this->createCompanyFor($ownerA);
        $this->fundWallet($companyA, 1000);

        $id = $this->withHeaders($this->authHeaders($ownerA))->postJson('/api/v1/withdrawals', [
            'amount' => 400,
            'provider' => 'mpesa',
            'destination_phone' => '0700111222',
        ])->assertCreated()->json('data.id');

        $ownerB = $this->createUser();
        $this->createCompanyFor($ownerB);

        $this->withHeaders($this->authHeaders($ownerB))->deleteJson('/api/v1/withdrawals/'.$id)
            ->assertNotFound();

        $this->assertSame('pending', Withdrawal::query()->findOrFail($id)->status);
    }

    private function fundWallet(Company $company, float $balance): Wallet
    {
        return Wallet::query()->updateOrCreate(
            ['company_id' => $company->id, 'currency' => 'TZS'],
            ['balance' => $balance, 'status' => 'active'],
        );
    }
}
