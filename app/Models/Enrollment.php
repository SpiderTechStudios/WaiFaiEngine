<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasUuids;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    public const STATUS_PROCESSING_PAYMENT = 'processing_payment';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const ACTIVE_RESERVATION_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAYMENT_FAILED,
        self::STATUS_PROCESSING_PAYMENT,
    ];

    /** @var list<string> */
    public const RETRYABLE_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAYMENT_FAILED,
    ];

    protected $table = 'signup_intents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reference',
        'status',
        'failed_payment_attempts',
        'subscription_fee',
        'total_amount',
        'currency',
        'business_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'payment_phone',
        'address',
        'portal_subdomain',
        'password_hash',
        'expires_at',
        'user_id',
        'company_id',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'failed_payment_attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast()
            || in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_CANCELLED], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->user_id !== null;
    }

    public function maxFailedAttempts(): int
    {
        return (int) config('platform.enrollment_max_failed_attempts', 3);
    }

    public function remainingAttempts(): int
    {
        return max(0, $this->maxFailedAttempts() - (int) $this->failed_payment_attempts);
    }

    public function canRetryPayment(): bool
    {
        return ! $this->isCompleted()
            && ! $this->isExpired()
            && in_array($this->status, self::RETRYABLE_STATUSES, true)
            && $this->remainingAttempts() > 0;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class, 'signup_intent_id');
    }
}
