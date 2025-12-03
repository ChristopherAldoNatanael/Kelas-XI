<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;
use App\Models\TeacherAttendance;

echo "Testing Pending Attendances API Logic\n";
echo "======================================\n\n";

$targetDate = Carbon::now()->format('Y-m-d');
$dayName = Carbon::parse($targetDate)->format('l');

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

echo "Date: $targetDate\n";
echo "Day: $hari\n\n";

$currentTime = Carbon::now()->format('H:i:s');

// Get all schedules for today
$schedules = Schedule::with(['guru:id,nama,nip'])
    ->where('hari', $hari)
    ->orderBy('kelas')
    ->orderBy('jam_mulai')
    ->get();

echo "Total schedules for $hari: " . $schedules->count() . "\n\n";

$result = [];
$errorCount = 0;

foreach ($schedules as $schedule) {
    try {
        // Check existing attendance
        $attendance = TeacherAttendance::where('schedule_id', $schedule->id)
            ->where('tanggal', $targetDate)
            ->first();

        // Extract time
        $jamMulai = null;
        $jamSelesai = null;

        if ($schedule->jam_mulai) {
            $jamMulai = $schedule->jam_mulai instanceof \DateTime
                ? $schedule->jam_mulai->format('H:i:s')
                : (string) $schedule->jam_mulai;
        }
        if ($schedule->jam_selesai) {
            $jamSelesai = $schedule->jam_selesai instanceof \DateTime
                ? $schedule->jam_selesai->format('H:i:s')
                : (string) $schedule->jam_selesai;
        }

        // Include schedules without attendance OR with pending status
        if (!$attendance || $attendance->status === 'pending') {
            $isPastSchedule = $jamSelesai && $currentTime > $jamSelesai;
            $isCurrentPeriod = $jamMulai && $jamSelesai &&
                $currentTime >= $jamMulai && $currentTime <= $jamSelesai;

            // Safely extract class info
            $classId = null;
            $className = $schedule->kelas ?? 'Unknown';
            try {
                if ($schedule->class_id) {
                    $classId = (int) $schedule->class_id;
                }
                $classRelation = $schedule->class;
                if ($classRelation && isset($classRelation->nama_kelas)) {
                    $className = (string) $classRelation->nama_kelas;
                    $classId = (int) $classRelation->id;
                }
            } catch (\Exception $e) {
                // Use fallback
            }

            // Safely extract subject name
            $subjectName = $schedule->mata_pelajaran ?? 'Unknown';
            try {
                $subjectRelation = $schedule->subject;
                if ($subjectRelation && isset($subjectRelation->nama)) {
                    $subjectName = (string) $subjectRelation->nama;
                }
            } catch (\Exception $e) {
                // Use fallback
            }

            // Safely extract teacher info
            $teacherName = 'Unknown';
            $teacherNip = '';
            try {
                if ($schedule->guru) {
                    $teacherName = (string) ($schedule->guru->nama ?? 'Unknown');
                    $teacherNip = (string) ($schedule->guru->nip ?? '');
                }
            } catch (\Exception $e) {
                // Use fallback
            }

            $item = [
                'id' => $attendance ? (int) $attendance->id : null,
                'schedule_id' => (int) $schedule->id,
                'date' => (string) $targetDate,
                'day' => (string) $hari,
                'time_start' => $jamMulai ? (string) $jamMulai : null,
                'time_end' => $jamSelesai ? (string) $jamSelesai : null,
                'class_id' => $classId,
                'class_name' => (string) $className,
                'subject_name' => (string) $subjectName,
                'teacher_id' => (int) $schedule->guru_id,
                'teacher_name' => (string) $teacherName,
                'teacher_nip' => (string) $teacherNip,
                'status' => $attendance ? (string) $attendance->status : 'belum_lapor',
                'has_attendance' => $attendance !== null,
                'is_past_schedule' => (bool) $isPastSchedule,
                'is_current_period' => (bool) $isCurrentPeriod,
            ];

            // Validate all fields are correct types
            foreach ($item as $key => $value) {
                if ($value !== null && !is_scalar($value) && !is_bool($value)) {
                    echo "ERROR: Field '$key' is not scalar: " . gettype($value) . "\n";
                    print_r($value);
                    $errorCount++;
                }
            }

            $result[] = $item;
        }
    } catch (\Exception $e) {
        echo "Error processing schedule ID {$schedule->id}: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\nProcessed " . count($result) . " pending items\n";
echo "Errors: $errorCount\n\n";

// Test JSON encoding
$json = json_encode(['data' => ['all_pending' => $result]], JSON_PRETTY_PRINT);
if ($json === false) {
    echo "JSON ENCODING FAILED: " . json_last_error_msg() . "\n";
} else {
    echo "JSON encoding successful!\n";
    echo "JSON size: " . strlen($json) . " bytes\n\n";

    // Show first 2 items
    echo "Sample items:\n";
    echo json_encode(array_slice($result, 0, 2), JSON_PRETTY_PRINT) . "\n";
}
