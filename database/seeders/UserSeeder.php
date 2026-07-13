<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
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
        // Get roles and departments
        $adminRole = Role::where('name', 'admin')->first();
        $supplyChainRole = Role::where('name', 'supply-chain')->first();
        $userRole = Role::where('name', 'user')->first();
        $accountUserRole = Role::where('name', 'account-user')->first();
        
        $financeDept = Department::where('name', 'Finance')->first();
        $supplyChainDept = Department::where('name', 'Supply Chain')->first();
        $salesDept = Department::where('name', 'Sales')->first();

        // Creating an Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@qgpos.com',
            'password' => 'Admin1122@',
            'role_id' => $adminRole?->id,
            'department_id' => $salesDept?->id,
        ]);

        // Creating a Supply Chain user
        User::create([
            'name' => 'Supply Chain User',
            'email' => 'supplychain@qgpos.com',
            'password' => 'password123',
            'role_id' => $supplyChainRole?->id,
            'department_id' => $supplyChainDept?->id,
        ]);

        // Creating a Regular User
        User::create([
            'name' => 'Regular User',
            'email' => 'user@qgpos.com',
            'password' => 'password123',
            'role_id' => $userRole?->id,
            'department_id' => $salesDept?->id,
        ]);

        // Creating a Regular User
        User::create([
            'name' => 'Syed Bilal',
            'email' => 'sbilal@qgpos.com',
            'password' => 'Bilal1122@',
            'role_id' => $userRole?->id,
            'department_id' => $salesDept?->id,
        ]);

        // Creating an Account User
        User::create([
            'name' => 'Account User',
            'email' => 'account@qgpos.com',
            'password' => 'Account123@',
            'role_id' => $accountUserRole?->id,
            'department_id' => $financeDept?->id,
        ]);
    }
}
