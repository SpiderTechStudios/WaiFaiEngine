<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProvider extends Model
{
    public const SLUG_FLUTTERWAVE = 'flutterwave';

    public const SLUG_PALMPAY = 'palmpay';

    public const SLUG_PALMPESA = 'palmpesa';

    public const SLUG_STUB = 'stub';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'supports_payments',
        'supports_payouts',
        'is_active',
        'is_default_for_payments',
        'is_default_for_payouts',
        'credentials',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports_payments' => 'boolean',
            'supports_payouts' => 'boolean',
            'is_active' => 'boolean',
            'is_default_for_payments' => 'boolean',
            'is_default_for_payouts' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::findBySlugOrFail((string) $value);
    }

    public static function findBySlugOrFail(string $slug): self
    {
        $provider = static::query()->where('slug', $slug)->first();

        if (! $provider) {
            abort(404, 'Payment provider not found.');
        }

        return $provider;
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials ?? [], $key, $default);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    /**
     * Safe public view of credentials (never secrets).
     *
     * @return array<string, mixed>
     */
    public function publicCredentials(): array
    {
        $credentials = $this->credentials ?? [];

        return array_filter([
            'public_key' => $credentials['public_key'] ?? null,
            'app_id' => $credentials['app_id'] ?? null,
            'api_base_url' => $credentials['api_base_url'] ?? null,
            'user_id' => $credentials['user_id'] ?? null,
            'has_secret_key' => filled($credentials['secret_key'] ?? null) || filled($credentials['api_token'] ?? null),
            'has_encryption_key' => filled($credentials['encryption_key'] ?? null),
            'has_webhook_secret' => filled($credentials['webhook_secret'] ?? null),
            'has_private_key' => filled($credentials['private_key'] ?? null),
            'has_app_id' => filled($credentials['app_id'] ?? null),
            'has_api_token' => filled($credentials['api_token'] ?? null) || filled($credentials['secret_key'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }
}
