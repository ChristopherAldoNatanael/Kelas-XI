<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    echo "🔍 Checking User Authentication Data...\n";
    echo "=====================================\n\n";

    // Check if users exist
    $users = DB::table('users')->get();

    if ($users->isEmpty()) {
        echo "❌ No users found in database!\n";
        echo "💡 Need to seed database with users.\n\n";

        // Create test admin user
        echo "🔧 Creating admin user...\n";
        $adminPassword = Hash::make('password');

        DB::table('users')->insert([
            'nama' => 'Administrator',
            'email' => 'admin@sekolah.com',
            'password' => $adminPassword,
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "✅ Admin user created successfully!\n";
        echo "📧 Email: admin@sekolah.com\n";
        echo "🔑 Password: password\n\n";
    } else {
        echo "👥 Found " . $users->count() . " users:\n\n";

        foreach ($users as $user) {
            echo "ID: {$user->id}\n";
            echo "Name: {$user->nama}\n";
            echo "Email: {$user->email}\n";
            echo "Role: {$user->role}\n";
            echo "Status: {$user->status}\n";
            echo "Password Hash: " . substr($user->password, 0, 20) . "...\n";
            echo "---\n";
        }

        // Test password hash for admin user
        $adminUser = DB::table('users')->where('email', 'admin@sekolah.com')->first();

        if ($adminUser) {
            echo "\n🔐 Testing password for admin@sekolah.com:\n";
            $testPasswords = ['password', 'admin', '123456', 'secret'];

            foreach ($testPasswords as $testPassword) {
                $isValid = Hash::check($testPassword, $adminUser->password);
                $status = $isValid ? "✅ VALID" : "❌ Invalid";
                echo "   '{$testPassword}' -> {$status}\n";

                if ($isValid) {
                    echo "\n🎉 FOUND CORRECT PASSWORD: '{$testPassword}'\n";
                    break;
                }
            }

            // Check if password hash looks correct
            if (strpos($adminUser->password, '$2y$') === 0) {
                echo "\n✅ Password hash format is correct (bcrypt)\n";
            } else {
                echo "\n❌ Password hash format looks wrong!\n";
                echo "Current hash: {$adminUser->password}\n";

                // Fix the password
                echo "\n🔧 Fixing admin password...\n";
                $newPasswordHash = Hash::make('password');
                DB::table('users')->where('email', 'admin@sekolah.com')->update([
                    'password' => $newPasswordHash,
                    'updated_at' => now()
                ]);
                echo "✅ Password fixed! Use 'password' to login.\n";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
