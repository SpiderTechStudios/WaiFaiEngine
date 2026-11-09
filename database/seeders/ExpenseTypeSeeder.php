<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseTypeSeeder extends Seeder
{
    /**
     * @var list<array{name: string, category: string}>
     */
    private array $types = [
        ['name' => 'Platform Subscription', 'category' => ExpenseType::CATEGORY_PLATFORM],

        ['name' => 'Hardware', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Bandwidth', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Electricity', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'SMS', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Salaries', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Marketing', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Rent', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Utilities', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Others', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
    ];

    /**
     * Old slug => new slug. Existing expenses are moved to the new type before
     * obsolete types are deleted, so historical records keep a valid category.
     *
     * @var array<string, string>
     */
    private array $legacyMap = [
        'internet' => 'bandwidth',
        'electricity' => 'electricity',
        'router-purchase' => 'hardware',
        'access-point-purchase' => 'hardware',
        'ups-purchase' => 'hardware',
        'switch-purchase' => 'hardware',
        'cable-accessories' => 'hardware',
        'installation' => 'others',
        'maintenance' => 'others',
        'repair' => 'hardware',
        'transport' => 'others',
        'technician' => 'others',
        'rent-site' => 'rent',
        'sim-data' => 'utilities',
        'other' => 'others',
    ];

    public function run(): void
    {
        $kept = [];

        foreach ($this->types as $type) {
            $slug = Str::slug($type['name']);

            $kept[$slug] = ExpenseType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'is_active' => true,
                ],
            );
        }

        $fallback = $kept['others'];

        ExpenseType::query()
            ->whereNotIn('slug', array_keys($kept))
            ->get()
            ->each(function (ExpenseType $type) use ($kept, $fallback) {
                $target = $kept[$this->legacyMap[$type->slug] ?? 'others'] ?? $fallback;

                Expense::withTrashed()
                    ->where('expense_type_id', $type->id)
                    ->update([
                        'expense_type_id' => $target->id,
                        'category' => $target->category,
                    ]);

                $type->delete();
            });
    }
}
