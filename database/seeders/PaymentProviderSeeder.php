<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use App\Services\PaymentProviderService;
use Illuminate\Database\Seeder;

class PaymentProviderSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PaymentProviderService::class);

        $stub = PaymentProvider::query()->updateOrCreate(
            ['slug' => PaymentProvider::SLUG_STUB],
            [
                'name' => 'Stub Provider',
                'description' => 'Local/testing payment provider (no external network calls).',
                'supports_payments' => true,
                'supports_payouts' => true,
                'is_active' => true,
                'credentials' => [
                    'webhook_secret' => config('platform.payment_webhook_secret') ?: 'stub-webhook-secret',
                ],
                'settings' => [],
            ]
        );

        $flutterwave = PaymentProvider::query()->updateOrCreate(
            ['slug' => PaymentProvider::SLUG_FLUTTERWAVE],
            [
                'name' => 'Flutterwave',
                'description' => 'Flutterwave V3 collections and transfers.',
                'supports_payments' => true,
                'supports_payouts' => true,
                'is_active' => true,
                'credentials' => array_filter([
                    'public_key' => config('services.flutterwave.public_key'),
                    'secret_key' => config('services.flutterwave.secret_key'),
                    'encryption_key' => config('services.flutterwave.encryption_key'),
                    'webhook_secret' => config('services.flutterwave.webhook_secret'),
                    'api_base_url' => config('services.flutterwave.base_url'),
                ]),
                'settings' => [
                    'mobile_money_charge_type' => 'mobile_money_tanzania',
                ],
            ]
        );

        // Prefer Flutterwave as platform default when a secret is configured; otherwise stub for local/tests.
        if (filled(config('services.flutterwave.secret_key'))) {
            $service->setDefaultForPayments($flutterwave->fresh());
            $service->setDefaultForPayouts($flutterwave->fresh());
            $stub->forceFill([
                'is_default_for_payments' => false,
                'is_default_for_payouts' => false,
            ])->save();
        } else {
            $service->setDefaultForPayments($stub->fresh());
            $service->setDefaultForPayouts($stub->fresh());
            $flutterwave->forceFill([
                'is_default_for_payments' => false,
                'is_default_for_payouts' => false,
            ])->save();
        }
    }
}
