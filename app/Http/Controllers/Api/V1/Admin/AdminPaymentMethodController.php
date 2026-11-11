<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddPaymentMethodPrefixRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminPaymentMethodController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        $methods = PaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success([
            'items' => PaymentMethodResource::collection($methods)->resolve(),
        ], 'Payment methods retrieved');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('prefixes', $data)) {
            $data['prefixes'] = $this->normalizePrefixes((array) $data['prefixes']);
            $this->assertPrefixesAvailable($data['prefixes'], $paymentMethod, 'prefixes');
        }

        $old = $paymentMethod->only(['name', 'prefixes', 'logo_url', 'is_active', 'sort_order']);

        $paymentMethod->fill($data)->save();

        $this->auditLogger->log(
            'payment_method_updated',
            $request->user(),
            null,
            PaymentMethod::class,
            $paymentMethod->id,
            oldValues: $old,
            newValues: $paymentMethod->fresh()->only(['name', 'prefixes', 'logo_url', 'is_active', 'sort_order']),
        );

        return $this->success(
            (new PaymentMethodResource($paymentMethod->fresh()))->resolve(),
            'Payment method updated',
        );
    }

    public function addPrefix(AddPaymentMethodPrefixRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $prefix = $this->normalizePrefix((string) $request->validated('prefix'));
        $prefixes = $paymentMethod->prefixes ?? [];

        if (! in_array($prefix, $prefixes, true)) {
            $this->assertPrefixesAvailable([$prefix], $paymentMethod, 'prefix');
        }

        if (in_array($prefix, $prefixes, true)) {
            return $this->success(
                (new PaymentMethodResource($paymentMethod))->resolve(),
                'Prefix is already configured',
            );
        }

        $prefixes[] = $prefix;
        sort($prefixes);

        $paymentMethod->forceFill(['prefixes' => array_values($prefixes)])->save();

        $this->auditLogger->log(
            'payment_method_prefix_added',
            $request->user(),
            null,
            PaymentMethod::class,
            $paymentMethod->id,
            newValues: ['prefix' => $prefix],
        );

        return $this->success(
            (new PaymentMethodResource($paymentMethod->fresh()))->resolve(),
            'Prefix added',
        );
    }

    public function removePrefix(PaymentMethod $paymentMethod, string $prefix): JsonResponse
    {
        $normalized = $this->normalizePrefix($prefix);
        $prefixes = $paymentMethod->prefixes ?? [];

        if (! in_array($normalized, $prefixes, true)) {
            throw ValidationException::withMessages([
                'prefix' => ['This prefix is not configured for the payment method.'],
            ]);
        }

        $prefixes = array_values(array_filter($prefixes, fn (string $value) => $value !== $normalized));

        $paymentMethod->forceFill(['prefixes' => $prefixes])->save();

        $this->auditLogger->log(
            'payment_method_prefix_removed',
            request()->user(),
            null,
            PaymentMethod::class,
            $paymentMethod->id,
            oldValues: ['prefix' => $normalized],
        );

        return $this->success(
            (new PaymentMethodResource($paymentMethod->fresh()))->resolve(),
            'Prefix removed',
        );
    }

    /**
     * @param  array<int, mixed>  $prefixes
     * @return list<string>
     */
    private function normalizePrefixes(array $prefixes): array
    {
        $normalized = [];

        foreach ($prefixes as $prefix) {
            if (! is_string($prefix) && ! is_numeric($prefix)) {
                throw ValidationException::withMessages([
                    'prefixes' => ['Each prefix must be a string such as 074.'],
                ]);
            }

            $normalized[] = $this->normalizePrefix((string) $prefix);
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * A phone prefix must map to exactly one mobile-money network.
     *
     * @param  list<string>  $prefixes
     */
    private function assertPrefixesAvailable(array $prefixes, PaymentMethod $current, string $errorKey): void
    {
        $others = PaymentMethod::query()
            ->whereKeyNot($current->id)
            ->get(['id', 'name', 'prefixes']);

        foreach ($prefixes as $prefix) {
            $owner = $others->first(
                fn (PaymentMethod $method) => in_array($prefix, $method->prefixes ?? [], true),
            );

            if ($owner) {
                throw ValidationException::withMessages([
                    $errorKey => ["Prefix {$prefix} is already assigned to {$owner->name}."],
                ]);
            }
        }
    }

    private function normalizePrefix(string $prefix): string
    {
        $digits = preg_replace('/\D+/', '', $prefix) ?? '';

        if (strlen($digits) === 2) {
            $digits = '0'.$digits;
        }

        if (! preg_match('/^0\d{2}$/', $digits)) {
            throw ValidationException::withMessages([
                'prefix' => ['Prefix must be a 3-digit Tanzania network prefix such as 074 or 068.'],
            ]);
        }

        return $digits;
    }
}
