<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 QUICK DATABASE CHECK\n\n";

// Check users table structure (SQLite compatible)
echo "📋 USERS TABLE STRUCTURE:\n";
$columns = DB::select("PRAGMA table_info(users)");
foreach ($columns as $col) {
    echo "  {$col->name} ({$col->type}) - Not Null: " . ($col->notnull ? 'YES' : 'NO') . "\n";
}

echo "\n👥 USERS IN DATABASE:\n";
$users = DB::select("SELECT id, name, email, role, is_banned, deleted_at FROM users");
if (count($users) === 0) {
    echo "❌ No users found!\n\n💡 RUN THESE COMMANDS TO SET UP:\n";
    echo "   php artisan migrate:fresh\n";
    echo "   php artisan db:seed --class=UserSeeder\n";
} else {
    foreach ($users as $user) {
        echo "  {$user->email} - {$user->name} - Role: {$user->role} - Banned: {$user->is_banned}\n";
    }

    echo "\n✅ DATABASE SETUP LOOKS GOOD\n";
}
