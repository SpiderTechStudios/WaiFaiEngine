<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerDevice extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'device_type',
        'mac_address',
        'status',
        'first_seen_at',
        'last_seen_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function networkSessions(): HasMany
    {
        return $this->hasMany(NetworkSession::class);
    }
}
