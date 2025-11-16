<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Checking foreign key constraints...\n\n";

try {
    // Check foreign key constraints for schedules table
    $constraints = DB::select("
        SELECT
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = 'db_sekolah'
            AND TABLE_NAME = 'schedules'
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    echo "📋 Foreign key constraints for schedules table:\n";
    foreach ($constraints as $constraint) {
        echo "- {$constraint->CONSTRAINT_NAME}: {$constraint->COLUMN_NAME} -> {$constraint->REFERENCED_TABLE_NAME}.{$constraint->REFERENCED_COLUMN_NAME}\n";
    }

    // Check if we have any teachers and users
    echo "\n👥 Current data count:\n";
    echo "- Teachers: " . DB::table('teachers')->count() . "\n";
    echo "- Users: " . DB::table('users')->count() . "\n";
    echo "- Schedules: " . DB::table('schedules')->count() . "\n";

    echo "\n👨‍🏫 Sample teacher IDs:\n";
    $teachers = DB::table('teachers')->select('id', 'name', 'mata_pelajaran')->limit(5)->get();
    foreach ($teachers as $teacher) {
        echo "- Teacher ID {$teacher->id}: {$teacher->name} ({$teacher->mata_pelajaran})\n";
    }

    echo "\n👥 Sample user IDs:\n";
    $users = DB::table('users')->select('id', 'name', 'role')->limit(5)->get();
    foreach ($users as $user) {
        echo "- User ID {$user->id}: {$user->name} ({$user->role})\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
