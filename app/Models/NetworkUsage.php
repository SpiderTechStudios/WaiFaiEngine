<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkUsage extends Model
{
    public $timestamps = false;

    protected $table = 'network_usage';

    protected $fillable = [
        'company_id',
        'network_session_id',
        'customer_id',
        'access_grant_id',
        'upload_bytes',
        'download_bytes',
        'recorded_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NetworkUsage $usage): void {
            $usage->created_at ??= now();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function networkSession(): BelongsTo
    {
        return $this->belongsTo(NetworkSession::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function accessGrant(): BelongsTo
    {
        return $this->belongsTo(AccessGrant::class);
    }
}
