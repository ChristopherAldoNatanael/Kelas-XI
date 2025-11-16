<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Checking actual database table structures...\n\n";

try {
    // Check schedules table
    echo "📅 SCHEDULES TABLE:\n";
    $schedules = DB::select('DESCRIBE schedules');
    foreach ($schedules as $column) {
        echo "- {$column->Field} ({$column->Type}) - {$column->Key}\n";
    }

    echo "\n👨‍🏫 TEACHERS TABLE:\n";
    $teachers = DB::select('DESCRIBE teachers');
    foreach ($teachers as $column) {
        echo "- {$column->Field} ({$column->Type}) - {$column->Key}\n";
    }

    echo "\n👥 USERS TABLE:\n";
    $users = DB::select('DESCRIBE users');
    foreach ($users as $column) {
        echo "- {$column->Field} ({$column->Type}) - {$column->Key}\n";
    }

    echo "\n🏫 CLASSES TABLE:\n";
    $classes = DB::select('DESCRIBE classes');
    foreach ($classes as $column) {
        echo "- {$column->Field} ({$column->Type}) - {$column->Key}\n";
    }

    echo "\n📚 SUBJECTS TABLE:\n";
    $subjects = DB::select('DESCRIBE subjects');
    foreach ($subjects as $column) {
        echo "- {$column->Field} ({$column->Type}) - {$column->Key}\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
