<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'company_id',
        'payment_transaction_id',
        'provider_reference',
        'phone_number',
        'amount',
        'status',
        'request_payload',
        'response_payload',
        'initiated_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
