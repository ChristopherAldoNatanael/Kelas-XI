<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== CHECK USER AUTHENTICATION ===\n\n";

$user = User::where('email', 'test@example.com')->first();

if (!$user) {
    echo "❌ User not found!\n";
    exit(1);
}

echo "User found:\n";
echo "  ID: {$user->id}\n";
echo "  Email: {$user->email}\n";
echo "  Name: {$user->nama}\n";
echo "  Role: {$user->role}\n";
echo "  Class ID: {$user->class_id}\n\n";

// Test password
$testPassword = 'password';
$matches = Hash::check($testPassword, $user->password);

echo "Password check for '$testPassword': " . ($matches ? "✅ MATCH" : "❌ NO MATCH") . "\n\n";

if (!$matches) {
    echo "Updating password to 'password'...\n";
    $user->password = Hash::make('password');
    $user->save();
    echo "✅ Password updated!\n";
}
