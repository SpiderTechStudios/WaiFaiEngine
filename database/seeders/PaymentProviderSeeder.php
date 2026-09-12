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

        PaymentProvider::query()->updateOrCreate(
            ['slug' => PaymentProvider::SLUG_PALMPAY],
            [
                'name' => 'PalmPay',
                'description' => 'PalmPay merchant collections (createorder) and payouts.',
                'supports_payments' => true,
                'supports_payouts' => true,
                'is_active' => true,
                'credentials' => array_filter([
                    'app_id' => config('services.palmpay.app_id'),
                    'private_key' => config('services.palmpay.private_key'),
                    'public_key' => config('services.palmpay.public_key'),
                    'api_base_url' => config('services.palmpay.base_url'),
                ]),
                'settings' => [
                    'country_code' => config('services.palmpay.country_code', 'NG'),
                    'version' => config('services.palmpay.version', 'V2'),
                    'product_type' => 'bank_transfer',
                    'goods_details' => '[{"goodsId":"-1"}]',
                    'minor_unit_factor' => 100,
                    'payout_path' => '/api/v2/payment/merchant/payout',
                ],
            ]
        );

        PaymentProvider::query()->updateOrCreate(
            ['slug' => PaymentProvider::SLUG_PALMPESA],
            [
                'name' => 'PalmPesa',
                'description' => 'PalmPesa Tanzania mobile-money collections (USSD/push + webhook + order-status polling).',
                'supports_payments' => true,
                'supports_payouts' => false,
                'is_active' => true,
                'credentials' => array_filter([
                    'secret_key' => config('services.palmpesa.api_token'),
                    'api_token' => config('services.palmpesa.api_token'),
                    'user_id' => config('services.palmpesa.user_id'),
                    'api_base_url' => config('services.palmpesa.base_url'),
                ], fn ($value) => filled($value)),
                'settings' => [
                    'vendor' => config('services.palmpesa.vendor', 'TILL61103867'),
                    'status_check_minutes' => (int) config('services.palmpesa.status_check_minutes', 4),
                    'default_address' => 'Dar es Salaam',
                    'default_postcode' => '11111',
                ],
            ]
        );

        // Prefer Flutterwave as platform default when a secret is configured; otherwise stub for local/tests.
        // PalmPay / PalmPesa are seeded active but must be set as default manually from the admin dashboard.
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

        PaymentProvider::query()
            ->whereIn('slug', [PaymentProvider::SLUG_PALMPAY, PaymentProvider::SLUG_PALMPESA])
            ->update([
                'is_default_for_payments' => false,
                'is_default_for_payouts' => false,
            ]);
    }
}
