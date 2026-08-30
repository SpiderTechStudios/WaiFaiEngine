<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignupIntent extends Model
{
    use HasUuids;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const SETUP_ASSISTED = 'assisted';

    public const SETUP_SELF = 'self';

    /** @var list<string> */
    public const ACTIVE_RESERVATION_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'status',
        'setup_type',
        'installation_fee',
        'subscription_fee',
        'total_amount',
        'currency',
        'business_name',
        'first_name',
        'last_name',
        'email',
        'phone',
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
            'installation_fee' => 'decimal:2',
            'subscription_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast()
            || in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_CANCELLED], true);
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
