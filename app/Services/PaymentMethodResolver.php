<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Support\NetworkDetector;
use Illuminate\Support\Collection;

class PaymentMethodResolver
{
    /**
     * Normalised aliases => canonical payment_methods.key.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'mpesa' => 'mpesa',
        'vodacom' => 'mpesa',
        'mpesatz' => 'mpesa',

        'airtelmoney' => 'airtelmoney',
        'airtel' => 'airtelmoney',
        'airteltz' => 'airtelmoney',

        'halopesa' => 'halopesa',
        'halotel' => 'halopesa',

        'mixxbyyas' => 'mixxbyyas',
        'mixx' => 'mixxbyyas',
        'yas' => 'mixxbyyas',
        'tigo' => 'mixxbyyas',
        'tigopesa' => 'mixxbyyas',
        'tigotz' => 'mixxbyyas',
        'zantel' => 'mixxbyyas',

        'tpesa' => 'tpesa',
        'ttcl' => 'tpesa',
        'ttclpesa' => 'tpesa',
    ];

    /**
     * @var Collection<int, PaymentMethod>|null
     */
    private ?Collection $methods = null;

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function all(): Collection
    {
        return $this->methods ??= PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findByKey(?string $key): ?PaymentMethod
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $this->all()->firstWhere('key', $key);
    }

    /**
     * Resolve a stored value (legacy network name, provider channel, or key) to
     * a canonical payment method. Optionally falls back to the phone prefix.
     */
    public function resolve(?string $value, ?string $phone = null): ?PaymentMethod
    {
        $key = $this->canonicalKey($value);

        if ($key !== null) {
            return $this->findByKey($key);
        }

        return $this->detect($phone);
    }

    public function idFor(?string $value, ?string $phone = null): ?int
    {
        return $this->resolve($value, $phone)?->id;
    }

    public function detect(?string $phone): ?PaymentMethod
    {
        $national = $this->normalizePhone($phone);

        if ($national !== null) {
            $prefix = substr($national, 0, 3);

            foreach ($this->all() as $method) {
                if (in_array($prefix, $method->prefixes ?? [], true)) {
                    return $method;
                }
            }
        }

        $detected = NetworkDetector::detect($phone);

        return $detected !== null ? $this->findByKey($this->canonicalKey($detected)) : null;
    }

    public function canonicalKey(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';

        return self::ALIASES[$normalized] ?? null;
    }

    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '255') && strlen($digits) >= 12) {
            $digits = '0'.substr($digits, 3);
        }

        if (strlen($digits) === 9 && in_array($digits[0], ['6', '7'], true)) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) !== 10 || ! str_starts_with($digits, '0')) {
            return null;
        }

        return $digits;
    }
}
