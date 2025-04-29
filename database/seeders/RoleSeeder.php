<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleEnum::cases() as $roleEnum) {
            Role::updateOrCreate(
                ['name' => $roleEnum->value], // Use enum value as the name
            );
        }
    }
}
