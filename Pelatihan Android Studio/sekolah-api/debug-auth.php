<?php

// Debug authentication issue comprehensively
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "🔍 COMPREHENSIVE AUTHENTICATION DEBUG\n";
echo "=====================================\n\n";

// 1. Check database connection
try {
    $user = DB::selectOne('SELECT 1');
    echo "✅ Database connection: OK\n";
} catch (Exception $e) {
    echo "❌ Database connection: FAILED - {$e->getMessage()}\n";
    exit(1);
}

// 2. Check users table structure
echo "\n📋 USERS TABLE STRUCTURE:\n";
$columns = DB::select("DESCRIBE users");
foreach ($columns as $col) {
    echo "  {$col->Field} ({$col->Type}) - Key: {$col->Key}\n";
}

// 3. Check user data
echo "\n👥 USERS IN DATABASE:\n";
$users = User::all();
if ($users->count() === 0) {
    echo "❌ No users found in database!\n\n";
    echo "💡 You need to run database seeders:\n";
    echo "   php artisan db:seed --class=UserSeeder\n";
    exit(1);
}

foreach ($users as $user) {
    echo "\n📧 User: {$user->email}\n";
    echo "   Name: {$user->name}\n";
    echo "   Role: {$user->role}\n";
    echo "   Is banned: " . ($user->is_banned ? 'YES' : 'NO') . "\n";
    echo "   Deleted at: " . ($user->deleted_at ? $user->deleted_at : 'NULL') . "\n";

    // Check password hash
    $hashedPassword = $user->password;
    $testPassword = 'password';
    $passwordCheck = password_verify($testPassword, $hashedPassword);
    echo "   Password hash starts: " . substr($hashedPassword, 0, 20) . "...\n";
    echo "   Password 'password' verify: " . ($passwordCheck ? '✅ PASS' : '❌ FAIL') . "\n";

    // Test Auth::attempt
    Auth::logout(); // Reset session
    $attempt = Auth::attempt(['email' => $user->email, 'password' => $testPassword]);
    echo "   Auth::attempt result: " . ($attempt ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
}

// 4. Check User model traits
echo "\n🏗️  USER MODEL ANALYSIS:\n";
$user = new User();
echo "Has SoftDeletes trait: " . (in_array('SoftDeletes', class_uses($user)) ? '✅ YES' : '❌ NO') . "\n";

// 5. Debug auth configuration
echo "\n⚙️  AUTH CONFIGURATION:\n";
echo "Default guard: " . config('auth.defaults.guard') . "\n";
echo "Web provider: " . config('auth.guards.web.provider') . "\n";
echo "Users model: " . config('auth.providers.users.model') . "\n";

// 6. Test specific scenarios
echo "\n🧪 TESTING SPECIFIC SCENARIOS:\n";

$testUser = User::where('email', 'admin@example.com')->first();
if (!$testUser) {
    echo "❌ Test user admin@example.com not found\n";
} else {
    // Test Direct Auth::attempt
    echo "Testing admin@example.com / password:\n";

    // Method 1: Standard Auth::attempt
    $result1 = Auth::attempt(['email' => 'admin@example.com', 'password' => 'password']);
    echo "  Method 1 (basic): " . ($result1 ? '✅ PASS' : '❌ FAIL') . "\n";

    // Method 2: With status check (if status exists)
    if (isset($testUser->status)) {
        $result2 = Auth::attempt(['email' => 'admin@example.com', 'password' => 'password', 'status' => 'active']);
        echo "  Method 2 (with status='active'): " . ($result2 ? '✅ PASS' : '❌ FAIL') . "\n";
    } else {
        echo "  Method 2: SKIPPED (no status column)\n";
    }

    // Method 3: Manual check
    $result3 = !$testUser->trashed() && $testUser->is_banned === false && password_verify('password', $testUser->password);
    echo "  Method 3 (manual): " . ($result3 ? '✅ PASS' : '❌ FAIL') . "\n";

    // User state details
    echo "  User soft deleted: " . ($testUser->trashed() ? 'YES' : 'NO') . "\n";
    echo "  User banned: " . ($testUser->is_banned ? 'YES' : 'NO') . "\n";

    Auth::logout();
}

echo "\n📝 SUMMARY:\n";
echo "If Auth::attempt is failing but passwords are correct,\n";
echo "                                                 check:\n";
echo "1. Soft Deletes trait on User model\n";
echo "2. User is_banned status\n";
echo "3. Database user table structure\n";
echo "4. Laravel configuration\n";

echo "\n✅ DEBUG COMPLETE\n";
