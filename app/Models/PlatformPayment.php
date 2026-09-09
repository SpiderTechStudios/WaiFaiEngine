<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPayment extends Model
{
    /** @deprecated Use TYPE_PLATFORM_SUBSCRIPTION */
    public const TYPE_SIGNUP = 'signup';

    public const TYPE_PLATFORM_SUBSCRIPTION = 'platform_subscription';

    public const TYPE_SUBSCRIPTION_RENEWAL = 'subscription_renewal';

    public const TYPE_INSTALLATION = 'installation';

    public const TYPE_DEVICE_PURCHASE = 'device_purchase';

    public const PURPOSE_PLATFORM_SUBSCRIPTION = 'platform_subscription';

    public const PURPOSE_SUBSCRIPTION_RENEWAL = 'subscription_renewal';

    public const PURPOSE_INSTALLATION_REQUEST = 'installation_request';

    public const PURPOSE_DEVICE_PURCHASE = 'device_purchase';

    public const PURPOSE_MARKETPLACE_ORDER = 'marketplace_order';

    public const PURPOSE_VOUCHER_PURCHASE = 'voucher_purchase';

    public const DIRECTION_COLLECTION = 'collection';

    public const DIRECTION_PAYOUT = 'payout';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'payment_provider_id',
        'provider_slug',
        'type',
        'purpose',
        'direction',
        'signup_intent_id',
        'installation_request_id',
        'order_id',
        'company_id',
        'reference',
        'external_reference',
        'provider_event_id',
        'amount',
        'currency',
        'payment_method',
        'phone',
        'status',
        'line_items',
        'metadata',
        'initiated_at',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'line_items' => 'array',
            'metadata' => 'array',
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function resolvePurpose(): string
    {
        $column = $this->attributes['purpose'] ?? null;

        return (string) ($column
            ?: (($this->metadata['payment_purpose'] ?? null) ?: $this->type));
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_PAID || $this->processed_at !== null;
    }

    public function isEnrollmentSubscription(): bool
    {
        return in_array($this->type, [
            self::TYPE_PLATFORM_SUBSCRIPTION,
            self::TYPE_SIGNUP,
        ], true)
            || $this->resolvePurpose() === self::PURPOSE_PLATFORM_SUBSCRIPTION;
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'payment_provider_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'signup_intent_id');
    }

    /** @deprecated Use enrollment() */
    public function signupIntent(): BelongsTo
    {
        return $this->enrollment();
    }

    public function installationRequest(): BelongsTo
    {
        return $this->belongsTo(InstallationRequest::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
