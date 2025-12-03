<?php
// Final test - verify pending attendances API response

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;
use App\Models\TeacherAttendance;

$targetDate = Carbon::now()->format('Y-m-d');
$dayMap = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hari = $dayMap[Carbon::now()->format('l')];
$currentTime = Carbon::now()->format('H:i:s');

echo "=== Testing Pending Attendances API ===\n";
echo "Date: $targetDate | Day: $hari | Time: $currentTime\n\n";

$schedules = Schedule::with(['guru:id,nama,nip'])
    ->where('hari', $hari)
    ->orderBy('kelas')
    ->orderBy('jam_mulai')
    ->get();

echo "Total schedules for $hari: " . $schedules->count() . "\n";

$result = [];
foreach ($schedules as $schedule) {
    $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
        ->where('tanggal', $targetDate)
        ->first();

    if (!$attendance || $attendance->status === 'pending') {
        $classId = null;
        $className = $schedule->kelas ?? 'Unknown';
        try {
            if ($schedule->class_id) $classId = (int) $schedule->class_id;
            $classRelation = $schedule->class;
            if ($classRelation && isset($classRelation->nama_kelas)) {
                $className = (string) $classRelation->nama_kelas;
                $classId = (int) $classRelation->id;
            }
        } catch (\Exception $e) {
        }

        $subjectName = $schedule->mata_pelajaran ?? 'Unknown';
        $teacherName = $schedule->guru->nama ?? 'Unknown';
        $teacherNip = $schedule->guru->nip ?? '';

        $result[] = [
            'id' => $attendance ? (int) $attendance->id : null,
            'schedule_id' => (int) $schedule->id,
            'date' => (string) $targetDate,
            'day' => (string) $hari,
            'class_id' => $classId,
            'class_name' => (string) $className,
            'subject_name' => (string) $subjectName,
            'teacher_id' => (int) $schedule->guru_id,
            'teacher_name' => (string) $teacherName,
            'teacher_nip' => (string) $teacherNip,
            'status' => $attendance ? (string) $attendance->status : 'belum_lapor',
            'has_attendance' => $attendance !== null,
        ];
    }
}

// Group by class WITH schedules
$groupedByClass = collect($result)->groupBy('class_name')->map(function ($items, $className) {
    $firstItem = $items->first();
    return [
        'class_name' => (string) $className,
        'class_id' => isset($firstItem['class_id']) ? (int) $firstItem['class_id'] : null,
        'total_pending' => (int) $items->count(),
        'belum_lapor_count' => (int) $items->where('status', 'belum_lapor')->count(),
        'pending_count' => (int) $items->where('status', 'pending')->count(),
        'schedules' => $items->values()->toArray() // <-- This should have items now!
    ];
})->values()->toArray();

echo "\n=== Response Structure ===\n";
echo "grouped_by_class count: " . count($groupedByClass) . "\n";
echo "all_pending count: " . count($result) . "\n\n";

// Show first class group
if (count($groupedByClass) > 0) {
    $firstGroup = $groupedByClass[0];
    echo "First class group:\n";
    echo "  - class_name: {$firstGroup['class_name']}\n";
    echo "  - total_pending: {$firstGroup['total_pending']}\n";
    echo "  - schedules count: " . count($firstGroup['schedules']) . "\n";

    if (count($firstGroup['schedules']) > 0) {
        echo "\n  First schedule item:\n";
        $firstSchedule = $firstGroup['schedules'][0];
        foreach ($firstSchedule as $key => $value) {
            $displayValue = is_null($value) ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
            echo "    - $key: $displayValue\n";
        }
    }
}

// Test JSON encoding
$response = [
    'success' => true,
    'data' => [
        'date' => $targetDate,
        'day' => $hari,
        'total_pending' => count($result),
        'grouped_by_class' => $groupedByClass,
        'all_pending' => $result
    ]
];

$json = json_encode($response);
if ($json === false) {
    echo "\n❌ JSON ENCODING FAILED: " . json_last_error_msg() . "\n";
} else {
    echo "\n✅ JSON encoding successful!\n";
    echo "Response size: " . strlen($json) . " bytes (" . round(strlen($json) / 1024, 2) . " KB)\n";
}
