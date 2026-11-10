<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsMessage extends Model
{
    public const STATUSES = [
        'queued',
        'pending',
        'sent',
        'delivered',
        'failed',
        'rejected',
        'expired',
        'unknown',
    ];

    protected $fillable = [
        'provider',
        'provider_message_id',
        'reference',
        'sender',
        'recipient',
        'text',
        'status',
        'status_group',
        'http_status',
        'request_payload',
        'response_payload',
        'sent_at',
        'delivered_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryReports(): HasMany
    {
        return $this->hasMany(SmsDeliveryReport::class);
    }
}
