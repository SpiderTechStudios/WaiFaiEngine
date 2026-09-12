<?php

namespace App\Services;

use App\Models\PaymentProvider;
use App\Models\PlatformPayment;
use App\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentProviderService
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentProvider
    {
        return DB::transaction(function () use ($data) {
            $name = trim((string) $data['name']);
            $slug = Str::slug((string) ($data['slug'] ?? $name));

            // Check if provider already exists
            $exists = PaymentProvider::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->orWhere('slug', $slug)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => 'A payment provider with this name already exists.',
                ]);
            }

            $provider = PaymentProvider::query()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'supports_payments' => (bool) ($data['supports_payments'] ?? true),
                'supports_payouts' => (bool) ($data['supports_payouts'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_default_for_payments' => false,
                'is_default_for_payouts' => false,
                'credentials' => $this->normalizeCredentials(
                    $data['credentials'] ?? []
                ),
                'settings' => array_merge(
                    ['driver' => $slug],
                    is_array($data['settings'] ?? null) ? $data['settings'] : [],
                ),
            ]);

            if (!empty($data['is_default_for_payments'])) {
                $this->setDefaultForPayments($provider);
            }

            if (!empty($data['is_default_for_payouts'])) {
                $this->setDefaultForPayouts($provider);
            }

            $this->auditLogger->log(
                'payment_provider_created',
                null,
                null,
                PaymentProvider::class,
                $provider->id,
                newValues: ['slug' => $provider->slug],
            );

            return $provider->fresh();
        });
    }
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentProvider $provider, array $data): PaymentProvider
    {
        return DB::transaction(function () use ($provider, $data) {
            $credentials = $provider->credentials ?? [];
            if (array_key_exists('credentials', $data) && is_array($data['credentials'])) {
                $credentials = $this->mergeCredentials($credentials, $data['credentials']);
            }

            $name = array_key_exists('name', $data)
                ? trim((string) $data['name'])
                : $provider->name;

            $settings = array_key_exists('settings', $data)
                ? ($data['settings'] ?? [])
                : ($provider->settings ?? []);

            // Keep a stable driver key so renaming does not break provider integrations.
            if (! filled(data_get($settings, 'driver'))) {
                $settings['driver'] = data_get($provider->settings, 'driver', $provider->slug);
            }

            $slug = $provider->slug;
            $oldSlug = $provider->slug;

            if (array_key_exists('name', $data) && $name !== $provider->name) {
                $slug = $this->uniqueSlugFromName($name, $provider->id);
            }

            $provider->forceFill([
                'name' => $name,
                'slug' => $slug,
                'description' => array_key_exists('description', $data) ? $data['description'] : $provider->description,
                'supports_payments' => array_key_exists('supports_payments', $data)
                    ? (bool) $data['supports_payments']
                    : $provider->supports_payments,
                'supports_payouts' => array_key_exists('supports_payouts', $data)
                    ? (bool) $data['supports_payouts']
                    : $provider->supports_payouts,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $provider->is_active,
                'credentials' => $credentials,
                'settings' => $settings,
            ])->save();

            if ($slug !== $oldSlug) {
                PlatformPayment::query()
                    ->where('provider_slug', $oldSlug)
                    ->update(['provider_slug' => $slug]);
            }

            if (array_key_exists('is_default_for_payments', $data) && $data['is_default_for_payments']) {
                $this->setDefaultForPayments($provider->fresh());
            }

            if (array_key_exists('is_default_for_payouts', $data) && $data['is_default_for_payouts']) {
                $this->setDefaultForPayouts($provider->fresh());
            }

            $this->auditLogger->log(
                'payment_provider_updated',
                null,
                null,
                PaymentProvider::class,
                $provider->id,
                newValues: [
                    'name' => $provider->name,
                    'slug' => $provider->slug,
                ],
            );

            return $provider->fresh();
        });
    }

    public function enable(PaymentProvider $provider): PaymentProvider
    {
        $provider->forceFill(['is_active' => true])->save();

        return $provider->fresh();
    }

    public function disable(PaymentProvider $provider): PaymentProvider
    {
        if ($provider->is_default_for_payments || $provider->is_default_for_payouts) {
            throw ValidationException::withMessages([
                'provider' => ['Disable after assigning another default provider.'],
            ]);
        }

        $provider->forceFill(['is_active' => false])->save();

        return $provider->fresh();
    }

    public function delete(PaymentProvider $provider): void
    {
        if ($provider->is_default_for_payments || $provider->is_default_for_payouts) {
            throw ValidationException::withMessages([
                'provider' => ['Cannot delete the default payment or payout provider.'],
            ]);
        }

        if ($provider->payments()->exists()) {
            $provider->forceFill(['is_active' => false])->save();

            return;
        }

        $provider->delete();
    }

    public function setDefaultForPayments(PaymentProvider $provider): PaymentProvider
    {
        if (!$provider->is_active || !$provider->supports_payments) {
            throw ValidationException::withMessages([
                'provider' => ['Provider must be active and support collections.'],
            ]);
        }

        return DB::transaction(function () use ($provider) {
            PaymentProvider::query()
                ->where('is_default_for_payments', true)
                ->whereKeyNot($provider->id)
                ->update(['is_default_for_payments' => false]);

            $provider->forceFill(['is_default_for_payments' => true])->save();

            return $provider->fresh();
        });
    }

    public function setDefaultForPayouts(PaymentProvider $provider): PaymentProvider
    {
        if (!$provider->is_active || !$provider->supports_payouts) {
            throw ValidationException::withMessages([
                'provider' => ['Provider must be active and support payouts.'],
            ]);
        }

        return DB::transaction(function () use ($provider) {
            PaymentProvider::query()
                ->where('is_default_for_payouts', true)
                ->whereKeyNot($provider->id)
                ->update(['is_default_for_payouts' => false]);

            $provider->forceFill(['is_default_for_payouts' => true])->save();

            return $provider->fresh();
        });
    }

    /**
     * Dry-run a provider collection. Hits the live provider API but never persists a payment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function testCollection(PaymentProvider $provider, array $data): array
    {
        if (! $provider->is_active || ! $provider->supports_payments) {
            throw ValidationException::withMessages([
                'provider' => ['Provider must be active and support collections.'],
            ]);
        }

        $driver = app(PaymentProviderManager::class)->driverFor($provider);

        $reference = 'TEST-'.strtoupper(Str::random(10));
        $payment = new PlatformPayment([
            'payment_provider_id' => $provider->id,
            'provider_slug' => $provider->slug,
            'type' => PlatformPayment::TYPE_PLATFORM_SUBSCRIPTION,
            'purpose' => 'provider_test',
            'direction' => PlatformPayment::DIRECTION_COLLECTION,
            'reference' => $reference,
            'amount' => $data['amount'],
            'currency' => strtoupper((string) ($data['currency'] ?? config('platform.currency', 'TZS'))),
            'payment_method' => 'mobile_money',
            'phone' => $data['phone'],
            'status' => PlatformPayment::STATUS_PENDING,
            'metadata' => [
                'customer_name' => $data['name'] ?? 'Test Customer',
                'customer_email' => $data['email'] ?? 'test@waifai.local',
                'customer_address' => $data['address'] ?? 'Dar es Salaam',
                'customer_postcode' => $data['postcode'] ?? '11111',
                'provider_test' => true,
            ],
            'initiated_at' => now(),
        ]);

        $result = $driver->initiateCollection($payment);

        $this->auditLogger->log(
            'payment_provider_test_collection',
            null,
            null,
            PaymentProvider::class,
            $provider->id,
            newValues: [
                'slug' => $provider->slug,
                'test_reference' => $reference,
                'accepted' => $result->accepted,
                'provider_reference' => $result->providerReference,
            ],
        );

        return [
            'provider' => $provider->slug,
            'persisted' => false,
            'test_reference' => $reference,
            'accepted' => $result->accepted,
            'provider_reference' => $result->providerReference,
            'message' => $result->message,
            'provider_response' => $result->raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function normalizeCredentials(array $credentials): array
    {
        return array_filter([
            'public_key' => $credentials['public_key'] ?? null,
            'secret_key' => $credentials['secret_key'] ?? null,
            'encryption_key' => $credentials['encryption_key'] ?? null,
            'webhook_secret' => $credentials['webhook_secret'] ?? null,
            'api_base_url' => $credentials['api_base_url'] ?? null,
            'app_id' => $credentials['app_id'] ?? null,
            'private_key' => $credentials['private_key'] ?? null,
            'api_token' => $credentials['api_token'] ?? null,
            'user_id' => $credentials['user_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeCredentials(array $existing, array $incoming): array
    {
        foreach ([
            'public_key',
            'secret_key',
            'encryption_key',
            'webhook_secret',
            'api_base_url',
            'app_id',
            'private_key',
            'api_token',
            'user_id',
        ] as $key) {
            if (array_key_exists($key, $incoming) && filled($incoming[$key])) {
                $existing[$key] = $incoming[$key];
            }
        }

        return $existing;
    }

    private function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            throw ValidationException::withMessages([
                'name' => ['Name must produce a valid slug.'],
            ]);
        }

        $slug = $base;
        $suffix = 2;

        while (
            PaymentProvider::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
