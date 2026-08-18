<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\PaymentTransaction;
use App\Models\RevenueRecord;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Company $company, array $data, User $actor): PaymentTransaction
    {
        $plan = InternetPlan::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['internet_plan_id']);

        $price = $plan->prices()->where('status', 'active')->latest('id')->first();
        $amount = $data['amount'] ?? $price?->amount;
        $currency = $data['currency'] ?? $price?->currency ?? 'TZS';
        $status = $data['status'] ?? 'paid';

        return DB::transaction(function () use ($company, $plan, $price, $amount, $currency, $status, $data, $actor) {
            $customer = $this->findOrCreateCustomer($company, $data);

            $payment = PaymentTransaction::query()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'plan_price_id' => $price?->id,
                'reference' => 'PAY-'.strtoupper(Str::random(10)),
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $data['payment_method'] ?? 'mobile_money',
                'status' => $status,
                'initiated_at' => now(),
                'paid_at' => $status === 'paid' ? now() : null,
                'metadata' => [
                    'recorded_by' => $actor->id,
                ],
            ]);

            if ($status === 'paid') {
                $this->recognizePayment($company, $payment);
            }

            $this->auditLogger->log('payment_created', $actor, $company->id, PaymentTransaction::class, $payment->id);

            return $payment->load(['customer', 'internetPlan']);
        });
    }

    private function findOrCreateCustomer(Company $company, array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->where('company_id', $company->id)->findOrFail($data['customer_id']);
        }

        $query = Customer::query()->where('company_id', $company->id);
        if (! empty($data['customer_phone'])) {
            $existing = (clone $query)->where('phone', $data['customer_phone'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return Customer::query()->create([
            'company_id' => $company->id,
            'name' => $data['customer_name'] ?? 'Walk-in customer',
            'phone' => $data['customer_phone'] ?? null,
            'email' => $data['customer_email'] ?? null,
            'status' => 'active',
        ]);
    }

    private function recognizePayment(Company $company, PaymentTransaction $payment): void
    {
        $wallet = Wallet::query()->firstOrCreate(
            ['company_id' => $company->id, 'currency' => $payment->currency],
            ['balance' => 0, 'status' => 'active'],
        );

        $before = $wallet->balance;
        $wallet->forceFill(['balance' => $wallet->balance + $payment->amount])->save();

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $payment->amount,
            'balance_before' => $before,
            'balance_after' => $wallet->balance,
            'reference_type' => PaymentTransaction::class,
            'reference_id' => $payment->id,
            'description' => 'Payment '.$payment->reference,
        ]);

        RevenueRecord::query()->create([
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'source' => $payment->payment_method === 'voucher' ? 'voucher' : 'mobile_money',
            'payment_transaction_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'recognized',
            'recognized_at' => now(),
            'description' => 'Payment '.$payment->reference,
        ]);
    }
}
