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

    public const PURPOSE_PLATFORM_SUBSCRIPTION = 'platform_subscription';

    public const PURPOSE_SUBSCRIPTION_RENEWAL = 'subscription_renewal';

    public const PURPOSE_INSTALLATION_REQUEST = 'installation_request';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'type',
        'signup_intent_id',
        'installation_request_id',
        'company_id',
        'reference',
        'external_reference',
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
        ];
    }

    public function purpose(): string
    {
        return (string) (($this->metadata['payment_purpose'] ?? null) ?: $this->type);
    }

    public function isEnrollmentSubscription(): bool
    {
        return in_array($this->type, [
            self::TYPE_PLATFORM_SUBSCRIPTION,
            self::TYPE_SIGNUP,
        ], true)
            || $this->purpose() === self::PURPOSE_PLATFORM_SUBSCRIPTION;
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
