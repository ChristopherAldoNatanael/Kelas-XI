<?php

/**
 * Debug script for testing the Selasa (Tuesday) day filter
 * Run with: php test_selasa_endpoint.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Carbon\Carbon;

echo "=== DEBUG: Schedules With Attendance for Selasa ===\n\n";

// Simulate the same logic as schedulesWithAttendance method
$weekOffset = 0;
$dayFilter = 'Selasa';

$today = Carbon::now();
$startOfWeek = $today->copy()->startOfWeek()->addWeeks($weekOffset);
$endOfWeek = $startOfWeek->copy()->endOfWeek();

$dayMap = [
    'Senin' => 0,
    'Selasa' => 1,
    'Rabu' => 2,
    'Kamis' => 3,
    'Jumat' => 4,
    'Sabtu' => 5,
];

$targetDate = $startOfWeek->copy()->addDays($dayMap[$dayFilter]);

echo "Week: {$startOfWeek->format('Y-m-d')} to {$endOfWeek->format('Y-m-d')}\n";
echo "Target date for $dayFilter: {$targetDate->format('Y-m-d')}\n\n";

// Count schedules for Selasa
$scheduleCount = Schedule::where('hari', $dayFilter)->count();
echo "Total schedules for $dayFilter: $scheduleCount\n\n";

// Build the full response data
$schedules = Schedule::where('hari', $dayFilter)
    ->orderBy('kelas')
    ->orderBy('jam_mulai')
    ->get();

echo "Schedules retrieved: " . $schedules->count() . "\n\n";

$periodCounter = [];
$result = [];

foreach ($schedules as $schedule) {
    $className = $schedule->kelas ?? 'Unknown';
    if (!isset($periodCounter[$className])) {
        $periodCounter[$className] = 0;
    }
    $periodCounter[$className]++;
    $periodNum = $periodCounter[$className];

    $teacher = Teacher::find($schedule->guru_id);

    $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
        ->where('tanggal', $targetDate->format('Y-m-d'))
        ->first();

    $substituteTeacher = null;
    if ($attendance && in_array($attendance->status, ['izin', 'diganti']) && $attendance->guru_pengganti_id) {
        $substitute = Teacher::find($attendance->guru_pengganti_id);
        $substituteTeacher = $substitute ? $substitute->nama : null;
    }

    $result[] = [
        'schedule_id' => $schedule->id,
        'class_id' => $schedule->class_id ?? 0,
        'class_name' => $className,
        'subject_name' => $schedule->mata_pelajaran ?? 'Unknown',
        'teacher_name' => $teacher ? $teacher->nama : 'Unknown',
        'period' => $periodNum,
        'time_start' => $schedule->jam_mulai ?? '00:00',
        'time_end' => $schedule->jam_selesai ?? '00:00',
        'day_of_week' => $schedule->hari ?? '',
        'attendance_status' => $attendance ? $attendance->status : null,
        'attendance_time' => $attendance ? $attendance->waktu_hadir : null,
        'substitute_teacher' => $substituteTeacher
    ];
}

echo "Result items: " . count($result) . "\n\n";

// Build final response
$response = [
    'success' => true,
    'message' => 'Data jadwal dengan kehadiran berhasil diambil',
    'data' => $result
];

$jsonResponse = json_encode($response);

if ($jsonResponse === false) {
    echo "JSON ENCODE ERROR: " . json_last_error_msg() . "\n";
} else {
    echo "JSON response length: " . strlen($jsonResponse) . " bytes\n";
    echo "JSON response is valid: Yes\n\n";

    // Check if there's any issue at position 8852
    if (strlen($jsonResponse) >= 8852) {
        echo "Character at position 8852: '" . substr($jsonResponse, 8850, 10) . "'\n";
    }

    // Verify it can be decoded
    $decoded = json_decode($jsonResponse, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON DECODE ERROR: " . json_last_error_msg() . "\n";
    } else {
        echo "JSON can be decoded: Yes\n";
        echo "Data count after decode: " . count($decoded['data']) . "\n";
    }
}

echo "\n=== Sample of data (first 3 items) ===\n";
print_r(array_slice($result, 0, 3));

// Also check for any UTF-8 encoding issues
echo "\n=== Checking for encoding issues ===\n";
foreach ($result as $index => $item) {
    foreach ($item as $key => $value) {
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            echo "Invalid UTF-8 at item $index, key $key: $value\n";
        }
    }
}
echo "Encoding check complete.\n";
