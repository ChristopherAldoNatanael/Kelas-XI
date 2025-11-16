<?php

use Illuminate\Support\Facades\Hash;
use App\Models\User;

// Test login credentials
$testCredentials = [
    'email' => 'admin@sekolah.com',
    'password' => 'password'
];

echo "🔐 Testing Login Credentials...\n";
echo "===============================\n\n";

// Find user
$user = User::where('email', $testCredentials['email'])->first();

if (!$user) {
    echo "❌ User not found: " . $testCredentials['email'] . "\n";
    exit;
}

echo "👤 User found:\n";
echo "   ID: " . $user->id . "\n";
echo "   Name: " . $user->nama . "\n";
echo "   Email: " . $user->email . "\n";
echo "   Role: " . $user->role . "\n";
echo "   Status: " . $user->status . "\n\n";

// Test password
echo "🔑 Testing password...\n";
$passwordCheck = Hash::check($testCredentials['password'], $user->password);

if ($passwordCheck) {
    echo "✅ PASSWORD IS CORRECT!\n";
    echo "🎉 You can now login with:\n";
    echo "   Email: " . $testCredentials['email'] . "\n";
    echo "   Password: " . $testCredentials['password'] . "\n";
} else {
    echo "❌ Password is wrong!\n";
    echo "Hash in DB: " . $user->password . "\n";
    echo "Testing with: " . $testCredentials['password'] . "\n";

    // Try to fix it
    echo "\n🔧 Fixing password...\n";
    $user->password = Hash::make($testCredentials['password']);
    $user->save();
    echo "✅ Password updated! Try logging in again.\n";
}

// Check user status
if ($user->status !== 'active') {
    echo "\n⚠️  WARNING: User status is '{$user->status}' (should be 'active')\n";
    echo "🔧 Activating user...\n";
    $user->status = 'active';
    $user->save();
    echo "✅ User activated!\n";
}
