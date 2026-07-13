<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserDataSeeder extends Seeder
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
        $managerRole = Role::where('name', 'line-manager')->first();
        
        $salesDept = Department::where('name', 'Sales')->first();

        // Employee data from the provided list
        $employees = [
            [
                'name' => 'Usman Akbar',
                'email' => 'usman.akbar@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Khurram Ali',
                'email' => 'khurram.ali@qgpos.com',
                'password' => 'password123',
                'role_id' => $managerRole?->id ?? $adminRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Umair Quadri',
                'email' => 'umair.quadri@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Asim Javed',
                'email' => 'asim.javed@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Fahad Khan',
                'email' => 'fahad.khan@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Imran Shah',
                'email' => 'imran.shah@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Waqar Ali',
                'email' => 'waqar.ali@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Abdul Rafay',
                'email' => 'abdul.rafay@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'M. Adnan',
                'email' => 'm.adnan@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'M. Asim',
                'email' => 'm.asim@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Mobeen Ghouri',
                'email' => 'mobeen.ghouri@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ],
            [
                'name' => 'Tajammul Ahmed',
                'email' => 'tajammul.ahmed@qgpos.com',
                'password' => 'password123',
                'role_id' => $userRole?->id,
                'department_id' => $salesDept?->id,
            ]
        ];

        // Create employees
        foreach ($employees as $employee) {
            User::create($employee);
        }

        $this->command->info('Created ' . count($employees) . ' employee records successfully.');
    }
}