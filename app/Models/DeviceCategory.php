<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
