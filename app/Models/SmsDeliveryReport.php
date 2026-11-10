<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsDeliveryReport extends Model
{
    protected $fillable = [
        'sms_message_id',
        'provider_message_id',
        'reference',
        'recipient',
        'status',
        'status_group',
        'error_name',
        'error_description',
        'done_at',
        'sent_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'done_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class, 'sms_message_id');
    }
}
