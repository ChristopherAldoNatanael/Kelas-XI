<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

echo "Testing Schedule model...\n";

try {
    $schedules = \App\Models\Schedule::take(5)->get();
    echo "Found " . $schedules->count() . " schedules\n";

    if ($schedules->count() > 0) {
        $schedule = $schedules->first();
        echo "First schedule:\n";
        echo "- ID: " . $schedule->id . "\n";
        echo "- Hari: " . $schedule->hari . "\n";
        echo "- Kelas: " . $schedule->kelas . "\n";
        echo "- Mata Pelajaran: " . $schedule->mata_pelajaran . "\n";
        echo "- Guru ID: " . $schedule->guru_id . "\n";

        // Test relationship
        $guru = $schedule->guru;
        if ($guru) {
            echo "- Guru: " . ($guru->nama ?? $guru->name ?? 'Unknown') . "\n";
        } else {
            echo "- Guru: Not found\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";
