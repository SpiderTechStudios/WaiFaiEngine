<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = [
            "first_name" => "Super",
            "last_name" => "Admin",
            "email" => "admin@waifai.co.tz", 
            "phone" => "255754000000",
            "email_verified_at" => now(),
            "password" => bcrypt("admin@waifai"),
            "is_superadmin" => true,
            "status" => "active",
        ];

        User::create($superAdmin);
    }
}
