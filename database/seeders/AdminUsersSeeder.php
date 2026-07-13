<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the two QA / dev admin users.
 *
 *   qa@qgpos.com       Hello@123   admin
 *   app_dev@qgpos.com  Hello@123   admin
 *
 * Idempotent — re-running updates the password/role if changed but doesn't
 * create duplicates.
 *
 * Run:
 *   php artisan db:seed --class=AdminUsersSeeder
 */
class AdminUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve the admin Role record (id) — User model has both a string
        // `role` column and a `role_id` FK; we set both so all checks pass.
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $users = [
            [
                'name'  => 'QA Admin',
                'email' => 'qa@qgpos.com',
            ],
            [
                'name'  => 'App Dev Admin',
                'email' => 'app_dev@qgpos.com',
            ],
        ];

        foreach ($users as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name'              => $row['name'],
                    'password'          => Hash::make('Hello@123'),
                    'role'              => 'admin',
                    'role_id'           => $adminRole->id,
                    'email_verified_at' => now(),
                ]
            );

            $this->command->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')
                . " admin user: {$user->email}");
        }
    }
}
