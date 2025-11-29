<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "🔑 Testing Authentication Fix...\n\n";

// Test with the seeded admin user
$email = 'admin@example.com';
$password = 'password';

echo "Testing login for: {$email}\n\n";

$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ User not found: {$email}\n";
    exit(1);
}

echo "✅ User found: {$user->name}\n";
echo "Role: {$user->role}\n";
echo "Deleted at: " . ($user->deleted_at ? $user->deleted_at : 'NULL') . "\n";
echo "Banned: " . ($user->is_banned ? 'YES' : 'NO') . "\n";
echo "Has SoftDeletes: " . (in_array('SoftDeletes', class_uses(User::class)) ? 'YES' : 'NO') . "\n\n";

// Test authentication
$result = Auth::attempt(['email' => $email, 'password' => $password]);

if ($result) {
    echo "🎉 SUCCESS! Auth::attempt() returned true\n";

    $authenticatedUser = Auth::user();
    echo "Authenticated as: {$authenticatedUser->name} ({$authenticatedUser->role})\n";

    // Clean up
    Auth::logout();
    echo "\n✅ Authentication working correctly!\n";
} else {
    echo "❌ FAILED! Auth::attempt() returned false\n\n";

    // Debug why
    echo "🔍 Debugging...\n";

    // Check password directly
    $passwordCheck = password_verify($password, $user->password);
    echo "Password verify: " . ($passwordCheck ? '✅ PASS' : '❌ FAIL') . "\n";

    // Check if user is soft deleted
    echo "Is soft deleted: " . ($user->trashed() ? 'YES' : 'NO') . "\n";

    // Try manual query like Auth would do
    $manualUser = User::where('email', $email)->whereNull('deleted_at')->first();
    echo "Manual query with deleted_at IS NULL: " . ($manualUser ? 'FOUND' : 'NOT FOUND') . "\n";

    if ($manualUser && password_verify($password, $manualUser->password)) {
        echo "Manual auth check: ✅ WOULD WORK\n";
    } else {
        echo "Manual auth check: ❌ WOULD FAIL\n";
    }

    exit(1);
}

echo "\n✅ AUTHENTICATION FIXED SUCCESSFULLY!\n";
