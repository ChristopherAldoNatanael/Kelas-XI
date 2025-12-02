<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TeacherAttendance;
use Carbon\Carbon;

try {
    echo "Testing attendanceHistory query...\n\n";

    $query = TeacherAttendance::with([
        'schedule',
        'schedule.class',
        'schedule.subject:id,nama',
        'guru:id,nama,nip',
        'guruAsli:id,nama,nip'
    ])
        ->orderBy('tanggal', 'desc')
        ->orderBy('created_at', 'desc')
        ->limit(5);

    $attendances = $query->get();

    echo "Found " . $attendances->count() . " records\n\n";

    foreach ($attendances as $attendance) {
        echo "Record ID: " . $attendance->id . "\n";
        echo "  - tanggal: " . ($attendance->tanggal ?? 'NULL') . "\n";
        echo "  - schedule: " . ($attendance->schedule ? 'EXISTS' : 'NULL') . "\n";

        if ($attendance->schedule) {
            echo "  - schedule->hari: " . ($attendance->schedule->hari ?? 'NULL') . "\n";
            echo "  - schedule->jam_mulai: " . ($attendance->schedule->jam_mulai ?? 'NULL') . "\n";
            echo "  - schedule->jam_selesai: " . ($attendance->schedule->jam_selesai ?? 'NULL') . "\n";
            echo "  - schedule->class: " . ($attendance->schedule->class ? 'EXISTS' : 'NULL') . "\n";
            echo "  - schedule->subject: " . ($attendance->schedule->subject ? 'EXISTS' : 'NULL') . "\n";
        }

        echo "  - guru: " . ($attendance->guru ? $attendance->guru->nama : 'NULL') . "\n";
        echo "  - guruAsli: " . ($attendance->guruAsli ? $attendance->guruAsli->nama : 'NULL') . "\n";
        echo "  - status: " . ($attendance->status ?? 'NULL') . "\n";
        echo "  - jam_masuk: " . ($attendance->jam_masuk ?? 'NULL') . "\n";
        echo "\n";
    }

    // Now test the actual mapping
    echo "\n\nTesting mapping...\n";

    foreach ($attendances as $attendance) {
        echo "Record ID: " . $attendance->id . "\n";

        try {
            $time = $attendance->schedule ?
                ($attendance->schedule->jam_mulai . ' - ' . $attendance->schedule->jam_selesai) : null;
            echo "  - time: " . ($time ?? 'NULL') . "\n";

            $className = $attendance->schedule->class->nama_kelas ?? 'Unknown';
            echo "  - class_name: " . $className . "\n";

            if ($attendance->jam_masuk) {
                echo "  - jam_masuk raw: " . $attendance->jam_masuk . "\n";
                $arrivalTime = Carbon::parse($attendance->jam_masuk)->format('H:i');
                echo "  - arrival_time: " . $arrivalTime . "\n";
            }
        } catch (\Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
