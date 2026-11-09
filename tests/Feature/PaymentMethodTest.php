<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\PaymentMethodResolver;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    public function test_canonical_payment_methods_are_seeded(): void
    {
        $this->assertSame(
            ['mixxbyyas', 'mpesa', 'airtelmoney', 'halopesa', 'tpesa'],
            PaymentMethod::query()->orderBy('sort_order')->pluck('key')->all(),
        );
    }

    public function test_resolver_detects_canonical_network_from_phone_prefix(): void
    {
        $resolver = app(PaymentMethodResolver::class);

        $this->assertSame('mpesa', $resolver->detect('0754123456')?->key);
        $this->assertSame('mpesa', $resolver->detect('255754123456')?->key);
        $this->assertSame('airtelmoney', $resolver->detect('0784123456')?->key);
        $this->assertSame('halopesa', $resolver->detect('0621234567')?->key);
        $this->assertSame('mixxbyyas', $resolver->detect('0714257454')?->key);
        $this->assertSame('mixxbyyas', $resolver->detect('0772123456')?->key);
        $this->assertSame('tpesa', $resolver->detect('0731234567')?->key);
        $this->assertNull($resolver->detect('0729999999'));
    }

    public function test_resolver_maps_legacy_network_names(): void
    {
        $resolver = app(PaymentMethodResolver::class);

        $this->assertSame('mpesa', $resolver->resolve('Mpesa')?->key);
        $this->assertSame('airtelmoney', $resolver->resolve('AirtelMoney')?->key);
        $this->assertSame('halopesa', $resolver->resolve('Halopesa')?->key);
        $this->assertSame('mixxbyyas', $resolver->resolve('MixxByYas')?->key);
        $this->assertSame('mixxbyyas', $resolver->resolve('tigopesa')?->key);
        $this->assertSame('mixxbyyas', $resolver->resolve('zantel')?->key);
        $this->assertSame('tpesa', $resolver->resolve('TTCL')?->key);
        $this->assertNull($resolver->resolve('voucher'));
    }

    public function test_payment_transaction_stores_payment_method_fk(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $plan = $this->plan($company);

        $response = $this->withHeaders($this->authHeaders($owner))->postJson('/api/v1/payments', [
            'internet_plan_id' => $plan->id,
            'customer_name' => 'Asha Mohamed',
            'customer_phone' => '0754123456',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'status' => 'paid',
        ])->assertCreated();

        $this->assertSame('Mpesa', $response->json('data.payment_method'));
        $this->assertSame(
            $this->idFor('mpesa'),
            $response->json('data.payment_method_id'),
        );
    }

    public function test_withdrawal_and_payout_account_store_payment_method_fk(): void
    {
        $owner = $this->createUser(['phone' => '0754123456']);
        $company = $this->createCompanyFor($owner);
        $this->fundWallet($company, 1000);
        $headers = $this->authHeaders($owner);

        $accountId = $this->withHeaders($headers)->postJson('/api/v1/withdrawals/payout-accounts', [
            'name' => 'Asha Mohamed',
            'phone' => '0754123456',
        ])->assertCreated()
            ->assertJsonPath('data.network', 'mpesa')
            ->assertJsonPath('data.payment_method_id', $this->idFor('mpesa'))
            ->json('data.id');

        $withdrawalId = $this->withHeaders($headers)->postJson('/api/v1/withdrawals', [
            'amount' => 200,
            'payout_account_id' => $accountId,
        ])->assertCreated()
            ->assertJsonPath('data.payment_method_id', $this->idFor('mpesa'))
            ->json('data.id');

        $this->assertSame($this->idFor('mpesa'), Withdrawal::query()->findOrFail($withdrawalId)->payment_method_id);
    }

    public function test_backfill_maps_legacy_payment_method_strings_and_leaves_voucher_null(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $mpesa = $this->legacyPayment($company, 'Mpesa');
        $airtel = $this->legacyPayment($company, 'AirtelMoney');
        $mixx = $this->legacyPayment($company, 'MixxByYas');
        $voucher = $this->legacyPayment($company, 'voucher');

        $this->artisan('payment-methods:backfill')->assertSuccessful();

        $this->assertSame($this->idFor('mpesa'), $mpesa->fresh()->payment_method_id);
        $this->assertSame($this->idFor('airtelmoney'), $airtel->fresh()->payment_method_id);
        $this->assertSame($this->idFor('mixxbyyas'), $mixx->fresh()->payment_method_id);
        $this->assertNull($voucher->fresh()->payment_method_id);
    }

    private function idFor(string $key): int
    {
        return (int) PaymentMethod::query()->where('key', $key)->value('id');
    }

    private function plan(Company $company): InternetPlan
    {
        return InternetPlan::query()->create([
            'company_id' => $company->id,
            'name' => 'Daily '.Str::random(4),
            'slug' => 'daily-'.Str::lower(Str::random(6)),
            'duration' => 24,
            'duration_unit' => 'HOURS',
            'price' => 1000,
            'status' => InternetPlan::STATUS_ACTIVE,
        ]);
    }

    private function legacyPayment(Company $company, string $method): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'company_id' => $company->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'payment_method' => $method,
            'status' => 'paid',
            'initiated_at' => now(),
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
