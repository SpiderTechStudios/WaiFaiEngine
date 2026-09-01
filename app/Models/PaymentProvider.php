<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProvider extends Model
{
    public const SLUG_FLUTTERWAVE = 'flutterwave';

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
            'api_base_url' => $credentials['api_base_url'] ?? null,
            'has_secret_key' => filled($credentials['secret_key'] ?? null),
            'has_encryption_key' => filled($credentials['encryption_key'] ?? null),
            'has_webhook_secret' => filled($credentials['webhook_secret'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }
}
