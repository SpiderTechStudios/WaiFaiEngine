<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCompany>
 */
class UserCompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'role_id' => fn () => Role::query()->where('slug', 'operator')->whereNull('company_id')->value('id'),
            'status' => 'active',
            'joined_at' => now(),
        ];
    }
}
