<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseType extends Model
{
    public const CATEGORY_PLATFORM = 'platform';

    public const CATEGORY_OPERATIONAL = 'operational';

    public const CATEGORIES = [
        self::CATEGORY_PLATFORM,
        self::CATEGORY_OPERATIONAL,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
