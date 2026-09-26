<?php

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class ApiErrorMessage
{
    /**
     * Public labels for models that appear in API errors.
     *
     * @var array<class-string, string>
     */
    private const LABELS = [
        \App\Models\AccessGrant::class => 'Access grant',
        \App\Models\Brand::class => 'Brand',
        \App\Models\Device::class => 'Device',
        \App\Models\DeviceCategory::class => 'Device category',
        \App\Models\Order::class => 'Order',
        \App\Models\CartItem::class => 'Cart item',
        \App\Models\CaptiveSession::class => 'Captive session',
        \App\Models\Company::class => 'Company',
        \App\Models\Customer::class => 'Customer',
        \App\Models\CustomerDevice::class => 'Customer device',
        \App\Models\Enrollment::class => 'Enrollment',
        \App\Models\Expense::class => 'Expense',
        \App\Models\ExpenseType::class => 'Expense type',
        \App\Models\InstallationRequest::class => 'Installation request',
        \App\Models\InternetPlan::class => 'Package',
        \App\Models\Location::class => 'Branch',
        \App\Models\NetworkDevice::class => 'Router',
        \App\Models\NetworkSession::class => 'Session',
        \App\Models\NetworkStation::class => 'Network',
        \App\Models\PaymentProvider::class => 'Payment provider',
        \App\Models\PaymentTransaction::class => 'Payment',
        \App\Models\PlatformPayment::class => 'Payment',
        \App\Models\User::class => 'User',
        \App\Models\UserCompany::class => 'Staff member',
        \App\Models\Voucher::class => 'Voucher',
        \App\Models\Withdrawal::class => 'Withdrawal',
    ];

    public static function from(Throwable $e, string $fallback = 'Request failed.'): string
    {
        $model = self::modelClass($e);

        if ($model) {
            return self::notFound($model);
        }

        $message = trim($e->getMessage());

        if ($message !== '' && ! self::leaksInternals($message)) {
            return $message;
        }

        return $fallback;
    }

    public static function notFound(string $model): string
    {
        $label = self::LABELS[$model] ?? self::humanize(class_basename($model));

        return $label.' not found.';
    }

    private static function modelClass(Throwable $e): ?string
    {
        $current = $e;

        while ($current) {
            if ($current instanceof ModelNotFoundException && $current->getModel()) {
                return $current->getModel();
            }

            $current = $current->getPrevious();
        }

        if (preg_match('/No query results for model \[([^\]]+)\]/', $e->getMessage(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function leaksInternals(string $message): bool
    {
        return str_contains($message, 'App\\Models\\')
            || str_contains($message, 'No query results for model')
            || str_contains($message, 'SQLSTATE');
    }

    private static function humanize(string $classBasename): string
    {
        $words = strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $classBasename));

        return ucfirst($words);
    }
}
