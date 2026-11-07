<?php

namespace App\Support;

/**
 * Mobile-money reporting helpers shared by the payments API.
 */
final class PaymentProvider
{
    /**
     * Normalised mobile-network key, independent of the aggregator.
     */
    public static function key(?string $paymentMethod, ?string $network = null): string
    {
        $value = strtolower((string) ($network ?: $paymentMethod));
        $value = preg_replace('/[^a-z]/', '', $value) ?? '';

        return match (true) {
            str_contains($value, 'mpesa') || str_contains($value, 'vodacom') => 'mpesa',
            str_contains($value, 'mixx') || str_contains($value, 'yas') => 'mixx',
            str_contains($value, 'tigo') => 'tigo',
            str_contains($value, 'airtel') => 'airtel',
            str_contains($value, 'halo') => 'halopesa',
            str_contains($value, 'ttcl') => 'ttcl',
            default => 'other',
        };
    }

    /**
     * Raw `payment_method` values that belong to a normalised provider key.
     *
     * @return list<string>
     */
    public static function aliases(string $key): array
    {
        return match (strtolower($key)) {
            'mpesa' => ['mpesa', 'vodacom', 'm-pesa'],
            'mixx' => ['mixx', 'mixxbyyas', 'yas'],
            'tigo' => ['tigo', 'mixx', 'mixxbyyas'],
            'airtel' => ['airtel', 'airtelmoney'],
            'halopesa' => ['halopesa', 'halotel'],
            'ttcl' => ['ttcl'],
            'mobile_money', 'mobilemoney' => ['mobile_money', 'mobilemoney'],
            default => [strtolower($key)],
        };
    }

    /**
     * Canonical reporting status for any stored status value.
     */
    public static function statusKey(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        return match (true) {
            in_array($value, ['paid', 'success', 'successful', 'completed'], true) => 'paid',
            in_array($value, ['pending', 'processing', 'initiated'], true) => 'pending',
            in_array($value, ['failed', 'rejected', 'declined'], true) => 'failed',
            in_array($value, ['expired', 'timeout'], true) => 'expired',
            in_array($value, ['cancelled', 'canceled'], true) => 'cancelled',
            $value === 'refunded' => 'refunded',
            default => 'other',
        };
    }

    /**
     * Raw status values that belong to a canonical status key.
     *
     * @return list<string>
     */
    public static function statusAliases(string $key): array
    {
        return match (strtolower($key)) {
            'paid' => ['paid', 'success', 'successful', 'completed'],
            'pending' => ['pending', 'processing', 'initiated'],
            'failed' => ['failed', 'rejected', 'declined'],
            'expired' => ['expired', 'timeout'],
            'cancelled' => ['cancelled', 'canceled'],
            'refunded' => ['refunded'],
            default => [strtolower($key)],
        };
    }

    /**
     * All canonical status keys, in reporting order.
     *
     * @return list<string>
     */
    public static function statusKeys(): array
    {
        return ['paid', 'pending', 'failed', 'expired', 'cancelled', 'refunded', 'other'];
    }
}
