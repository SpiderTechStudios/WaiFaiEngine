<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'key',
        'name',
        'type',
        'logo_url',
        'prefixes',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prefixes' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
