<?php

namespace Database\Seeders;

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

        ['name' => 'Internet', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Electricity', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Router Purchase', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Access Point Purchase', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'UPS Purchase', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Switch Purchase', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Cable/Accessories', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Installation', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Maintenance', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Repair', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Transport', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Technician', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Rent/Site', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'SIM/Data', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
        ['name' => 'Other', 'category' => ExpenseType::CATEGORY_OPERATIONAL],
    ];

    public function run(): void
    {
        foreach ($this->types as $type) {
            ExpenseType::updateOrCreate(
                ['slug' => Str::slug($type['name'])],
                [
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'is_active' => true,
                ],
            );
        }
    }
}
