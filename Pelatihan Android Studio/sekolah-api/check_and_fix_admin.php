<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

echo "🔍 Checking Admin Users...\n";
echo "========================\n\n";

// Check existing admin users
$admins = User::where('role', 'admin')->get();
echo "Found " . count($admins) . " admin users:\n";

foreach ($admins as $admin) {
    echo "- {$admin->name} ({$admin->email})\n";
}

echo "\n";

// Check if admin@sekolah.com exists, if not create it
$defaultAdmin = User::where('email', 'admin@sekolah.com')->first();
if (!$defaultAdmin) {
    echo "❌ Default admin admin@sekolah.com not found. Creating...\n";

    $admin = User::create([
        'name' => 'Administrator',
        'email' => 'admin@sekolah.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active'
    ]);

    echo "✅ Admin created: {$admin->email} / password\n\n";
    $admins->push($admin);
}

// Test login for admin@sekolah.com
$testUser = User::where('email', 'admin@sekolah.com')->first();
if ($testUser) {
    echo "🔐 Testing login for admin@sekolah.com:\n";

    $passwords = ['password', 'admin123', '123456'];
    foreach ($passwords as $pwd) {
        $valid = Hash::check($pwd, $testUser->password);
        echo "  Password '$pwd': " . ($valid ? '✅ VALID' : '❌ Invalid') . "\n";
    }

    // If none work, reset to 'password'
    $resetNeeded = !Hash::check('password', $testUser->password);
    if ($resetNeeded) {
        echo "\n🔧 Resetting password to 'password'...\n";
        $testUser->update(['password' => Hash::make('password')]);
        echo "✅ Password reset complete\n";
    }
} else {
    echo "❌ Admin user admin@sekolah.com not found\n";
}

echo "\n🎯 Use these credentials:\n";
echo "Email: admin@sekolah.com\n";
echo "Password: password\n";
echo "Role: admin\n";
