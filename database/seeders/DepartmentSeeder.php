<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Sales'],
            ['name' => 'Supply Chain'],
            ['name' => 'Human Resources'],
            ['name' => 'Finance'],
            ['name' => 'IT Support'],
            ['name' => 'Marketing'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['name' => $department['name']],
                $department
            );
        }
    }
}
