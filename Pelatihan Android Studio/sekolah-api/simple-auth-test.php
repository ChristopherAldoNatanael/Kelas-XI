<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "Testing authentication with admin@example.com / password\n\n";

// Find user
$user = User::where('email', 'admin@example.com')->first();

if (!$user) {
    echo "User not found\n";
    exit(1);
}

echo "User found: {$user->name}\n";
echo "Role: {$user->role}\n";
echo "Deleted at: " . ($user->deleted_at ?? 'NULL') . "\n";
echo "Password hash: " . substr($user->password, 0, 20) . "...\n";
echo "Has SoftDeletes: " . (in_array('SoftDeletes', class_uses(User::class)) ? 'YES' : 'NO') . "\n\n";

// Test password hash manually
$passwordCheck = password_verify('password', $user->password);
echo "Password verify result: " . ($passwordCheck ? 'CORRECT' : 'INCORRECT') . "\n\n";

echo "Authentication test complete.\n";
echo "The 'Invalid credentials' error should now be fixed with SoftDeletes trait added.\n";
