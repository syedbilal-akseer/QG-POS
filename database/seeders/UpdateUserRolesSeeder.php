<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UpdateUserRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::where('name', $roleEnum->value)->first();

            if ($role) {
                // Update users with matching enum value
                User::where('role', $roleEnum->value)
                    ->update(['role_id' => $role->id]);
            }
        }
    }
}
