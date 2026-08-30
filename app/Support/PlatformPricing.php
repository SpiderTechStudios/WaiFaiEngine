<?php

namespace App\Support;

use InvalidArgumentException;

final class PlatformPricing
{
    public const SERVICE_INSTALLATION_ONLY = 'installation_only';

    public const SERVICE_ROUTER_AND_INSTALLATION = 'router_and_installation';

    public static function currency(): string
    {
        return (string) config('platform.currency', 'TZS');
    }

    public static function subscriptionMonthly(): float
    {
        return (float) config('platform.subscription_monthly');
    }

    public static function subscriptionGraceDays(): int
    {
        return (int) config('platform.subscription_grace_days', 7);
    }

    public static function installationUnitPrice(string $serviceType): float
    {
        $price = match ($serviceType) {
            self::SERVICE_INSTALLATION_ONLY => config('platform.installation.installation_only'),
            self::SERVICE_ROUTER_AND_INSTALLATION => config('platform.installation.router_and_installation'),
            default => null,
        };

        if ($price === null) {
            throw new InvalidArgumentException("Unknown installation service type [{$serviceType}].");
        }

        return (float) $price;
    }

    /**
     * @return array{service_type: string, quantity: int, unit_price: float, total_amount: float, currency: string}
     */
    public static function installationQuote(string $serviceType, int $quantity): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $unit = self::installationUnitPrice($serviceType);

        return [
            'service_type' => $serviceType,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'total_amount' => $unit * $quantity,
            'currency' => self::currency(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function installationServiceTypes(): array
    {
        return [
            self::SERVICE_INSTALLATION_ONLY,
            self::SERVICE_ROUTER_AND_INSTALLATION,
        ];
    }
}
