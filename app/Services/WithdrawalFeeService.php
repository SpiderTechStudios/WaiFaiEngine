<?php

namespace App\Services;

use App\Models\PaymentProvider;
use Illuminate\Validation\ValidationException;

class WithdrawalFeeService
{
    public function __construct(private PaymentMethodResolver $paymentMethods) {}

    public function detectNetwork(string $phone): ?string
    {
        return $this->paymentMethods->detect($phone)?->key;
    }

    public function normalizePhone(string $phone): string
    {
        return $this->paymentMethods->normalizePhone($phone) ?? '';
    }

    /**
     * @return array{
     *     amount: float,
     *     currency: string,
     *     platform_fee: float,
     *     provider_fee: float,
     *     fee_total: float,
     *     net_amount: float,
     *     platform_percent: float,
     *     platform_fixed: float,
     *     provider_percent: float,
     *     provider_fixed: float,
     *     network: string|null,
     *     payment_provider: array{slug: string, name: string}
     * }
     */
    public function quote(PaymentProvider $provider, float $amount, ?string $phone = null): array
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Withdrawal amount must be greater than zero.'],
            ]);
        }

        $config = $provider->payoutFeeConfig();

        $platformFee = round(($amount * $config['platform_percent'] / 100) + $config['platform_fixed'], 2);
        $providerFee = round(($amount * $config['provider_percent'] / 100) + $config['provider_fixed'], 2);
        $feeTotal = round($platformFee + $providerFee, 2);
        $netAmount = round($amount - $feeTotal, 2);

        $minimumNet = (float) config('withdrawals.minimum_net_amount', 1);

        if ($netAmount < $minimumNet) {
            throw ValidationException::withMessages([
                'amount' => [
                    'The withdrawal amount is too small after platform and provider fees. Increase the amount.',
                ],
            ]);
        }

        return [
            'amount' => $amount,
            'currency' => strtoupper((string) config('platform.currency', 'TZS')),
            'platform_fee' => $platformFee,
            'provider_fee' => $providerFee,
            'fee_total' => $feeTotal,
            'net_amount' => $netAmount,
            'platform_percent' => $config['platform_percent'],
            'platform_fixed' => $config['platform_fixed'],
            'provider_percent' => $config['provider_percent'],
            'provider_fixed' => $config['provider_fixed'],
            'network' => $phone !== null && $phone !== '' ? $this->detectNetwork($phone) : null,
            'payment_provider' => [
                'slug' => (string) $provider->slug,
                'name' => (string) $provider->name,
            ],
        ];
    }
}
