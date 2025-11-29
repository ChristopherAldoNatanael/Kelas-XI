<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 DATABASE STATUS CHECK\n\n";

// Check users table structure - this table has 'status' instead of 'is_banned'
echo "📋 USERS TABLE STRUCTURE:\n";
$columns = DB::select("PRAGMA table_info(users)");
foreach ($columns as $col) {
    echo "  {$col->name} ({$col->type})\n";
}

echo "\n👥 USERS IN DATABASE:\n";
$users = DB::select("SELECT id, name, email, role, status, deleted_at FROM users");
if (count($users) === 0) {
    echo "❌ No users found!\n\n💡 RUN THESE COMMANDS:\n";
    echo "   php artisan migrate:fresh\n";
    echo "   php artisan db:seed --class=UserSeeder\n";
    exit(1);
} else {
    foreach ($users as $user) {
        echo "  {$user->email} - {$user->name} - Role: {$user->role} - Status: {$user->status}\n";
    }

    echo "\n⚠️  ISSUE FOUND:\n";
    echo "   Database has 'status' column (values: active/inactive/suspended)\n";
    echo "   But User model expects 'is_banned' column (boolean)\n";
    echo "   And AuthController checks 'is_banned' field!\n\n";

    echo "🛠️  SOLUTION NEEDED:\n";
    echo "   Either update User model to use 'status' or update database to use 'is_banned'\n\n";
}
