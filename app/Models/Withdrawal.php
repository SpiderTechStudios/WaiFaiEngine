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
        'requested_by',
        'payment_gateway_id',
        'provider',
        'destination_phone',
        'destination_name',
        'reference',
        'external_reference',
        'amount',
        'currency',
        'status',
        'requested_at',
        'processed_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
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
