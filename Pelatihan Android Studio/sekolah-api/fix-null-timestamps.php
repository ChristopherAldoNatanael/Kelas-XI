<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING NULL TIMESTAMPS IN USERS TABLE ===\n\n";

try {
    // Check for users with null created_at
    $nullCreatedAt = DB::table('users')->whereNull('created_at')->get();
    echo "Users with NULL created_at: " . $nullCreatedAt->count() . "\n";

    if ($nullCreatedAt->count() > 0) {
        echo "Users with NULL created_at:\n";
        foreach ($nullCreatedAt as $user) {
            echo "  - ID: {$user->id}, Email: {$user->email}, Name: " . ($user->name ?? $user->nama ?? 'N/A') . "\n";
        }

        echo "\nFixing NULL created_at timestamps...\n";
        DB::table('users')
            ->whereNull('created_at')
            ->update([
                'created_at' => now(),
                'updated_at' => now()
            ]);
        echo "✓ Fixed NULL created_at timestamps\n";
    }

    // Check for users with null updated_at
    $nullUpdatedAt = DB::table('users')->whereNull('updated_at')->get();
    echo "\nUsers with NULL updated_at: " . $nullUpdatedAt->count() . "\n";

    if ($nullUpdatedAt->count() > 0) {
        echo "Fixing NULL updated_at timestamps...\n";
        DB::table('users')
            ->whereNull('updated_at')
            ->update(['updated_at' => now()]);
        echo "✓ Fixed NULL updated_at timestamps\n";
    }

    echo "\n=== FINAL CHECK ===\n";
    $totalUsers = DB::table('users')->count();
    $validTimestamps = DB::table('users')
        ->whereNotNull('created_at')
        ->whereNotNull('updated_at')
        ->count();

    echo "Total users: {$totalUsers}\n";
    echo "Users with valid timestamps: {$validTimestamps}\n";

    if ($totalUsers === $validTimestamps) {
        echo "✅ All users have valid timestamps!\n";
    } else {
        echo "❌ Some users still have invalid timestamps\n";
    }
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
