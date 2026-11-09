<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Withdrawal extends Model
{
    protected $fillable = [
        'company_id',
        'wallet_id',
        'payout_account_id',
        'payment_provider_id',
        'payment_provider_slug',
        'requested_by',
        'payment_gateway_id',
        'provider',
        'network',
        'payment_method_id',
        'destination_phone',
        'destination_name',
        'reference',
        'external_reference',
        'amount',
        'platform_fee',
        'provider_fee',
        'fee_total',
        'net_amount',
        'fee_breakdown',
        'currency',
        'status',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'otp_sent_at',
        'confirmed_at',
        'requested_at',
        'processed_at',
        'failed_at',
        'cancelled_at',
        'cancelled_by',
        'failure_reason',
        'metadata',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'provider_fee' => 'decimal:2',
            'fee_total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'fee_breakdown' => 'array',
            'otp_attempts' => 'integer',
            'otp_expires_at' => 'datetime',
            'otp_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function isAwaitingConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->confirmed_at === null
            && $this->otp_hash !== null;
    }

    public function isSettled(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'reference');
    }
}
