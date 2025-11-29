<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "🔧 Fixing Admin Login Issues\n";
echo "============================\n\n";

try {
    // Check users table structure
    echo "1. Checking users table structure...\n";
    $columns = DB::select('DESCRIBE users');
    $hasLastLogin = false;
    $hasPassword = false;

    foreach($columns as $col) {
        if($col->Field === 'last_login_at') {
            $hasLastLogin = true;
        }
        if($col->Field === 'password') {
            $hasPassword = true;
        }
    }

    echo "   - Password column: " . ($hasPassword ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "   - Last login column: " . ($hasLastLogin ? "✅ EXISTS" : "❌ MISSING") . "\n\n";

    // Get admin users
    echo "2. Finding admin users...\n";
    $admins = DB::table('users')->where('role', 'admin')->get();
    echo "   Found " . count($admins) . " admin users:\n";

    foreach($admins as $admin) {
        echo "   - {$admin->name} ({$admin->email})\n";
    }
    echo "\n";

    // Set default password for all admin users
    echo "3. Setting default passwords...\n";
    $defaultPassword = 'admin123';
    $hashedPassword = Hash::make($defaultPassword);

    foreach($admins as $admin) {
        DB::table('users')->where('id', $admin->id)->update([
            'password' => $hashedPassword,
            'updated_at' => now()
        ]);
        echo "   ✅ Set password for: {$admin->email}\n";
    }
    echo "\n";

    // Add last_login_at column if missing
    if (!$hasLastLogin) {
        echo "4. Adding last_login_at column...\n";
        DB::statement('ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL');
        echo "   ✅ Added last_login_at column\n\n";
    }

    // Test login
    echo "5. Testing login functionality...\n";
    $testUser = DB::table('users')->where('role', 'admin')->first();

    if ($testUser) {
        $loginSuccess = Hash::check($defaultPassword, $testUser->password);
        echo "   - Test login for {$testUser->email}: " . ($loginSuccess ? "✅ SUCCESS" : "❌ FAILED") . "\n";
    }

    echo "\n🎉 Admin login setup complete!\n";
    echo "===============================\n";
    echo "Default admin password: {$defaultPassword}\n\n";

    echo "Available admin accounts:\n";
    foreach($admins as $admin) {
        echo "- Email: {$admin->email}\n";
        echo "  Name: {$admin->name}\n";
        echo "  Password: {$defaultPassword}\n\n";
    }

    echo "You can now login to the web interface at: http://localhost:8000/login\n";

} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
