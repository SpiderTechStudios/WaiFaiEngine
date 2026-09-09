<?php

namespace App\Payments;

use App\Models\PaymentProvider;
use App\Payments\Drivers\FlutterwavePaymentProvider;
use App\Payments\Drivers\StubPaymentProvider;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentProviderManager
{
    public function driverFor(PaymentProvider $provider): PaymentProviderDriver
    {
        return match ($provider->slug) {
            PaymentProvider::SLUG_FLUTTERWAVE => new FlutterwavePaymentProvider($provider),
            PaymentProvider::SLUG_STUB => new StubPaymentProvider($provider),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$provider->slug}]."),
        };
    }

    public function driverBySlug(string $slug): PaymentProviderDriver
    {
        $provider = PaymentProvider::findBySlugOrFail($slug);

        return $this->driverFor($provider);
    }

    public function defaultForPayments(): PaymentProvider
    {
        $provider = PaymentProvider::query()
            ->where('is_active', true)
            ->where('supports_payments', true)
            ->where('is_default_for_payments', true)
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'payment_provider' => ['No default payment provider is configured.'],
            ]);
        }

        return $provider;
    }

    public function defaultForPayouts(): PaymentProvider
    {
        $provider = PaymentProvider::query()
            ->where('is_active', true)
            ->where('supports_payouts', true)
            ->where('is_default_for_payouts', true)
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'payment_provider' => ['No default payout provider is configured.'],
            ]);
        }

        return $provider;
    }
}
