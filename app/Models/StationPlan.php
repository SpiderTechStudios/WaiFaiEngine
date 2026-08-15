<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationPlan extends Model
{
    protected $fillable = [
        'company_id',
        'network_station_id',
        'internet_plan_id',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function networkStation(): BelongsTo
    {
        return $this->belongsTo(NetworkStation::class);
    }

    public function internetPlan(): BelongsTo
    {
        return $this->belongsTo(InternetPlan::class);
    }
}
