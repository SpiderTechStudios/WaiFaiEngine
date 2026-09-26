<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'email',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function latestDevice(): HasOne
    {
        return $this->hasOne(CustomerDevice::class)->latestOfMany('last_seen_at');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function currentAccessGrant(): HasOne
    {
        return $this->hasOne(AccessGrant::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', 'active')
                ->where(function ($inner): void {
                    $inner->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                }),
        );
    }

    public function networkSessions(): HasMany
    {
        return $this->hasMany(NetworkSession::class);
    }

    public function latestNetworkSession(): HasOne
    {
        return $this->hasOne(NetworkSession::class)->latestOfMany();
    }

    /**
     * Live hotspot session only (ended/expired sessions are excluded).
     */
    public function currentNetworkSession(): HasOne
    {
        return $this->hasOne(NetworkSession::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', 'active')
                ->whereHas('accessGrant', function ($grant): void {
                    $grant->where('status', 'active')
                        ->where(function ($inner): void {
                            $inner->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                }),
        );
    }
}