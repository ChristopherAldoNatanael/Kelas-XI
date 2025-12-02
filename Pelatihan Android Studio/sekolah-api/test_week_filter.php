<?php

/**
 * Test script untuk verifikasi week filter di Kurikulum Dashboard
 * Jalankan: php test_week_filter.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;

echo "=== TEST WEEK FILTER KURIKULUM DASHBOARD ===\n\n";

// Test hari ini
$today = Carbon::now();
$todayFormatted = $today->format('Y-m-d');
$todayDay = $today->isoFormat('dddd'); // English
$todayDayIndo = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'][$today->format('l')];

echo "Hari ini: $todayDayIndo, $todayFormatted\n";
echo "Day of week (ISO): " . $today->dayOfWeekIso . " (1=Senin, 7=Minggu)\n\n";

// Start of this week (Monday)
$startOfThisWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
$endOfThisWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

echo "Minggu ini:\n";
echo "  Start: " . $startOfThisWeek->format('Y-m-d') . " (" . $startOfThisWeek->format('l') . ")\n";
echo "  End: " . $endOfThisWeek->format('Y-m-d') . " (" . $endOfThisWeek->format('l') . ")\n\n";

// Test week offset -1 (minggu lalu)
$lastWeekStart = $startOfThisWeek->copy()->addWeeks(-1);
$lastWeekEnd = $endOfThisWeek->copy()->addWeeks(-1);

echo "Minggu lalu (week_offset = -1):\n";
echo "  Start: " . $lastWeekStart->format('Y-m-d') . " (" . $lastWeekStart->format('l') . ")\n";
echo "  End: " . $lastWeekEnd->format('Y-m-d') . " (" . $lastWeekEnd->format('l') . ")\n\n";

// Test calculating target date for each day in current week
echo "=== Target Date Calculation (Minggu Ini, week_offset=0) ===\n";
$days = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
$currentDayNum = $today->dayOfWeekIso;

foreach ($days as $dayName => $dayNum) {
    $targetDate = $startOfThisWeek->copy()->addDays($dayNum - 1);
    $isFuture = $targetDate->isAfter(Carbon::now());
    $status = $isFuture ? "❌ FUTURE (belum terjadi)" : "✅ PAST/TODAY";

    echo "  $dayName ($dayNum): " . $targetDate->format('Y-m-d') . " - $status\n";
}

echo "\n=== Target Date Calculation (Minggu Lalu, week_offset=-1) ===\n";
foreach ($days as $dayName => $dayNum) {
    $targetDate = $lastWeekStart->copy()->addDays($dayNum - 1);
    $isFuture = $targetDate->isAfter(Carbon::now());
    $status = $isFuture ? "❌ FUTURE" : "✅ PAST";

    echo "  $dayName ($dayNum): " . $targetDate->format('Y-m-d') . " - $status\n";
}

// Check attendance data
echo "\n=== Attendance Data Check ===\n";
$attendances = \App\Models\TeacherAttendance::orderBy('tanggal', 'desc')
    ->take(10)
    ->get(['id', 'schedule_id', 'tanggal', 'status']);

if ($attendances->isEmpty()) {
    echo "Tidak ada data attendance!\n";
} else {
    echo "10 Attendance terakhir:\n";
    foreach ($attendances as $att) {
        $tanggal = $att->tanggal instanceof Carbon ? $att->tanggal->format('Y-m-d') : $att->tanggal;
        echo "  ID: {$att->id} | Schedule: {$att->schedule_id} | Tanggal: $tanggal | Status: {$att->status}\n";
    }
}

echo "\n=== KESIMPULAN ===\n";
echo "Dengan perbaikan ini:\n";
echo "1. Kurikulum hanya melihat hari-hari yang sudah lewat di minggu ini\n";
echo "2. Untuk hari yang belum terjadi, status = 'belum' (gray)\n";
echo "3. Untuk melihat minggu lalu, gunakan tombol navigasi minggu\n";
echo "4. Cache di-clear setiap request dengan refresh=true\n";

echo "\nTest selesai!\n";
