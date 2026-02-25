<?php

// Run this with: php artisan tinker < create_user.php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create the user
$user = User::create([
    'name' => 'Owais Raza',
    'email' => 'owais.raza@quadri-group.com',
    'password' => Hash::make('HEllo@123'),
    'role' => 'cmd-khi',
    'email_verified_at' => now(),
]);

echo "User created successfully!\n";
echo "ID: " . $user->id . "\n";
echo "Name: " . $user->name . "\n";
echo "Email: " . $user->email . "\n";
echo "Role: " . $user->role . "\n";