<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Creating an Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@qgpos.com',
            'password' => 'Admin1122@',
            'role' => RoleEnum::ADMIN,
        ]);

        // Creating a Supply Chain user
        User::create([
            'name' => 'Supply Chain User',
            'email' => 'supplychain@qgpos.com',
            'password' => 'password123',
            'role' => RoleEnum::SUPPLY_CHAIN,
        ]);

        // Creating a Regular User
        User::create([
            'name' => 'Regular User',
            'email' => 'user@qgpos.com',
            'password' => 'password123',
            'role' => RoleEnum::USER,
        ]);

        // Creating a Regular User
        User::create([
            'name' => 'Syed Bilal',
            'email' => 'sbilal@qgpos.com',
            'password' => 'Bilal1122@',
            'role' => RoleEnum::USER,
        ]);
    }
}
