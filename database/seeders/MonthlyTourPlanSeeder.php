<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MonthlyTourPlan;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MonthlyTourPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salesperson = User::first(); // Assuming the first user is a salesperson

        MonthlyTourPlan::create([
            'salesperson_id' => $salesperson->salesperson_id,
            'date' => now(),
            'day' => now('l'),
            'from_location' => 'New York',
            'to_location' => 'Boston',
            'is_night_stay' => true,
            'key_tasks' => 'Meet with clients, follow up on sales inquiries',
        ]);
    }
}
