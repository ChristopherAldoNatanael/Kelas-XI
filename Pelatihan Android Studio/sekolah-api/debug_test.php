<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ClassModel;
use App\Models\Schedule;
use App\Models\TeacherAttendance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

try {
    $targetDate = '2025-06-09'; // Monday
    $filterDay = 'Senin';
    $filterClass = 2;

    echo "=== DEBUG START ===" . PHP_EOL;

    // Get class
    $classModel = ClassModel::find($filterClass);
    if (!$classModel) {
        echo "ERROR: Class not found" . PHP_EOL;
        exit(1);
    }
    echo "Class: " . $classModel->nama_kelas . PHP_EOL;

    // Get schedules
    $schedules = Schedule::with([
        'class',
        'subject:id,nama,kode',
        'teacher:id,nama,nip'
    ])
        ->where('hari', $filterDay)
        ->where('kelas', $classModel->nama_kelas)
        ->orderBy('jam_mulai')
        ->get();

    echo "Schedules count: " . $schedules->count() . PHP_EOL;

    if ($schedules->count() == 0) {
        echo "No schedules found!" . PHP_EOL;
        exit(0);
    }

    $scheduleIds = $schedules->pluck('id')->toArray();
    echo "Schedule IDs: " . implode(', ', $scheduleIds) . PHP_EOL;

    // Get attendances
    $attendances = TeacherAttendance::where('tanggal', $targetDate)
        ->whereIn('schedule_id', $scheduleIds)
        ->get()
        ->keyBy('schedule_id');

    echo "Attendances count: " . $attendances->count() . PHP_EOL;

    // Process each schedule
    foreach ($schedules as $schedule) {
        echo PHP_EOL . "--- Schedule ID: " . $schedule->id . " ---" . PHP_EOL;
        echo "  Subject: " . ($schedule->subject->nama ?? $schedule->mata_pelajaran ?? 'null') . PHP_EOL;
        echo "  Teacher: " . ($schedule->teacher->nama ?? 'null') . PHP_EOL;
        echo "  Class relation: " . ($schedule->class ? $schedule->class->id : 'null') . PHP_EOL;

        $attendance = $attendances->get($schedule->id);
        if ($attendance) {
            echo "  Attendance found: ID=" . $attendance->id . PHP_EOL;
            echo "  updated_at type: " . gettype($attendance->updated_at) . PHP_EOL;
            echo "  updated_at value: " . ($attendance->updated_at ?? 'NULL') . PHP_EOL;

            // Test the toISOString call
            if ($attendance->updated_at) {
                echo "  toISOString: " . $attendance->updated_at->toISOString() . PHP_EOL;
            }
        } else {
            echo "  No attendance record" . PHP_EOL;
        }
    }

    echo PHP_EOL . "=== DEBUG END ===" . PHP_EOL;
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . PHP_EOL;
    echo "Line: " . $e->getLine() . PHP_EOL;
    echo "Trace: " . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}
