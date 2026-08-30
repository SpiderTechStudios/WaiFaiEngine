<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationRequest extends Model
{
    public const SERVICE_INSTALLATION_ONLY = 'installation_only';

    public const SERVICE_ROUTER_AND_INSTALLATION = 'router_and_installation';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_CANCELLED = 'cancelled';

    public const FULFILLMENT_REQUESTED = 'requested';

    public const FULFILLMENT_PROCESSING = 'processing';

    public const FULFILLMENT_ON_SITE = 'on_site';

    public const FULFILLMENT_DELIVERED = 'delivered';

    public const FULFILLMENT_ACTIVE = 'active';

    public const FULFILLMENT_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const FULFILLMENT_FLOW = [
        self::FULFILLMENT_REQUESTED,
        self::FULFILLMENT_PROCESSING,
        self::FULFILLMENT_ON_SITE,
        self::FULFILLMENT_DELIVERED,
        self::FULFILLMENT_ACTIVE,
    ];

    protected $fillable = [
        'company_id',
        'requested_by',
        'reference',
        'service_type',
        'quantity',
        'unit_price',
        'total_amount',
        'currency',
        'payment_status',
        'fulfillment_status',
        'paid_at',
        'scheduled_at',
        'customer_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InstallationRequestItem::class)->orderBy('position');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InstallationRequestStatusHistory::class)->latest('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(InstallationRequestUpdate::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class, 'installation_request_id');
    }
}
