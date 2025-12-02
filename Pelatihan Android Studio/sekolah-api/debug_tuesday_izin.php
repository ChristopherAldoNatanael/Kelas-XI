<?php

/**
 * Debug script to verify izin counting for Tuesday (Selasa)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;

// Set locale to Indonesian
Carbon::setLocale('id');

$tuesday = Carbon::parse('2025-12-02');

echo "=== Debug Izin for Tuesday ===\n\n";
echo "Date: " . $tuesday->format('Y-m-d') . "\n";
echo "Day (isoFormat dddd): " . $tuesday->isoFormat('dddd') . "\n";
echo "Day (dayOfWeekIso): " . $tuesday->dayOfWeekIso . "\n";

// Check getDayName mapping
$dayMap = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];

$dayName = $dayMap[$tuesday->dayOfWeekIso];
echo "Day name (Indonesian): " . $dayName . "\n\n";

// Get approved leaves that cover this date
$leaves = \App\Models\Leave::where('status', 'approved')
    ->where('start_date', '<=', $tuesday->format('Y-m-d'))
    ->where('end_date', '>=', $tuesday->format('Y-m-d'))
    ->get();

echo "=== Approved Leaves covering $tuesday ===\n";
echo "Count: " . $leaves->count() . "\n\n";

foreach ($leaves as $leave) {
    echo "Leave ID: {$leave->id}\n";
    echo "  Teacher ID: {$leave->teacher_id}\n";
    echo "  Teacher: " . ($leave->teacher->nama ?? 'Unknown') . "\n";
    echo "  Start: {$leave->start_date}\n";
    echo "  End: {$leave->end_date}\n";
    echo "  Reason: {$leave->reason}\n";

    // Check schedules for this teacher on Tuesday
    $schedules = Schedule::where('guru_id', $leave->teacher_id)
        ->where('hari', $dayName)
        ->get();

    echo "  Schedules on $dayName: " . $schedules->count() . "\n";

    foreach ($schedules as $schedule) {
        echo "    - {$schedule->kelas} / {$schedule->mata_pelajaran} ({$schedule->jam_mulai} - {$schedule->jam_selesai})\n";
    }
    echo "\n";
}

// Calculate total izin count
$totalIzin = 0;
foreach ($leaves as $leave) {
    $scheduleCount = Schedule::where('guru_id', $leave->teacher_id)
        ->where('hari', $dayName)
        ->count();
    $totalIzin += $scheduleCount;
}

echo "=== Total izin count for Tuesday: $totalIzin ===\n";
