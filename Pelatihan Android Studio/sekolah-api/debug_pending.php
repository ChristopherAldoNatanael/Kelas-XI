<?php
// Debug: Check pending attendances

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;
use App\Models\TeacherAttendance;

$targetDate = Carbon::now()->format('Y-m-d');
$dayName = Carbon::now()->format('l');

$dayMap = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hari = $dayMap[$dayName] ?? $dayName;

echo "=== Debug Pending Attendances ===\n";
echo "Date: $targetDate\n";
echo "Day (English): $dayName\n";
echo "Day (Indonesian): $hari\n\n";

// Check schedules for today
$schedules = Schedule::with(['guru:id,nama,nip'])
    ->where('hari', $hari)
    ->orderBy('kelas')
    ->orderBy('jam_mulai')
    ->get();

echo "Total schedules for '$hari': " . $schedules->count() . "\n\n";

if ($schedules->count() === 0) {
    echo "⚠️ NO SCHEDULES FOUND for day '$hari'!\n\n";

    // List available days
    echo "Available days in database:\n";
    $days = Schedule::select('hari')->distinct()->pluck('hari')->toArray();
    foreach ($days as $d) {
        $count = Schedule::where('hari', $d)->count();
        echo "  - $d: $count schedules\n";
    }
    exit;
}

echo "Checking attendance status for each schedule:\n";
echo str_repeat("-", 80) . "\n";

$pendingCount = 0;
$hasAttendanceCount = 0;

foreach ($schedules as $schedule) {
    $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
        ->where('tanggal', $targetDate)
        ->first();

    $status = 'No attendance (belum_lapor)';
    if ($attendance) {
        $status = "Has attendance: {$attendance->status}";
        if ($attendance->status !== 'pending') {
            $hasAttendanceCount++;
        }
    }

    $shouldInclude = !$attendance || $attendance->status === 'pending';
    if ($shouldInclude) {
        $pendingCount++;
    }

    $teacherName = $schedule->guru->nama ?? 'Unknown';
    $className = $schedule->kelas ?? 'Unknown';
    $subject = $schedule->mata_pelajaran ?? 'Unknown';

    echo sprintf(
        "ID:%d | %s | %s | %s | %s | Include: %s\n",
        $schedule->id,
        str_pad($className, 12),
        str_pad($subject, 20),
        str_pad($teacherName, 20),
        $status,
        $shouldInclude ? 'YES' : 'NO'
    );
}

echo str_repeat("-", 80) . "\n";
echo "\nSummary:\n";
echo "  - Total schedules: " . $schedules->count() . "\n";
echo "  - Should show as pending: $pendingCount\n";
echo "  - Already has confirmed attendance: $hasAttendanceCount\n";

// Check if the response would be correct
echo "\n\n=== Simulating API Response ===\n";
$result = [];
foreach ($schedules as $schedule) {
    $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
        ->where('tanggal', $targetDate)
        ->first();

    if (!$attendance || $attendance->status === 'pending') {
        $result[] = [
            'schedule_id' => $schedule->id,
            'class_name' => $schedule->kelas,
            'subject_name' => $schedule->mata_pelajaran,
            'teacher_name' => $schedule->guru->nama ?? 'Unknown',
            'status' => $attendance ? 'pending' : 'belum_lapor'
        ];
    }
}

echo "API would return " . count($result) . " pending items\n";
echo json_encode(['total_pending' => count($result), 'first_3' => array_slice($result, 0, 3)], JSON_PRETTY_PRINT) . "\n";
