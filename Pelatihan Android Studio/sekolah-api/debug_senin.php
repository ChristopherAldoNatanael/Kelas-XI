<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TeacherAttendance;
use App\Models\Teacher;
use App\Models\Schedule;
use App\Models\Leave;

$date = '2025-12-01'; // Senin

echo "=== Data Attendance untuk Senin ($date) ===\n\n";

// Cek attendance records
$attendances = TeacherAttendance::where('tanggal', $date)->get();
echo "Total attendance records: " . $attendances->count() . "\n\n";

foreach ($attendances as $att) {
    $guru = Teacher::find($att->guru_id);
    $schedule = Schedule::find($att->schedule_id);
    echo "ID: {$att->id}\n";
    echo "  Guru: " . ($guru ? $guru->nama : 'Unknown') . " (ID: {$att->guru_id})\n";
    echo "  Status: {$att->status}\n";
    echo "  Kelas: " . ($schedule ? $schedule->kelas : 'N/A') . "\n";
    echo "  Mapel: " . ($schedule ? $schedule->mata_pelajaran : 'N/A') . "\n";
    echo "\n";
}

echo "=== Leaves yang aktif pada $date ===\n\n";

$leaves = Leave::where('status', 'approved')
    ->where('start_date', '<=', $date)
    ->where('end_date', '>=', $date)
    ->get();

echo "Total approved leaves: " . $leaves->count() . "\n\n";

foreach ($leaves as $leave) {
    $teacher = Teacher::find($leave->teacher_id);
    echo "Leave ID: {$leave->id}\n";
    echo "  Guru: " . ($teacher ? $teacher->nama : 'Unknown') . " (ID: {$leave->teacher_id})\n";
    echo "  Period: {$leave->start_date} to {$leave->end_date}\n";
    echo "  Reason: {$leave->reason}\n";

    // Cek jadwal guru ini di hari Senin
    $schedules = Schedule::where('guru_id', $leave->teacher_id)
        ->where('hari', 'Senin')
        ->get();
    echo "  Jadwal Senin: " . $schedules->count() . " slot\n";
    foreach ($schedules as $s) {
        echo "    - {$s->kelas} / {$s->mata_pelajaran}\n";
    }
    echo "\n";
}

echo "=== Jadwal X RPL 2 hari Senin ===\n\n";
$jadwalXRPL2 = Schedule::where('kelas', 'X RPL 2')
    ->where('hari', 'Senin')
    ->orderBy('jam_mulai')
    ->get();

foreach ($jadwalXRPL2 as $j) {
    $guru = Teacher::find($j->guru_id);

    // Cek apakah guru ini punya leave yang aktif
    $hasLeave = Leave::where('teacher_id', $j->guru_id)
        ->where('status', 'approved')
        ->where('start_date', '<=', $date)
        ->where('end_date', '>=', $date)
        ->exists();

    // Cek attendance
    $att = TeacherAttendance::where('schedule_id', $j->id)
        ->where('tanggal', $date)
        ->first();

    $attStatus = $att ? $att->status : 'NO RECORD';
    $leaveStatus = $hasLeave ? 'YES - IZIN' : 'NO';

    echo "{$j->jam_mulai}-{$j->jam_selesai}: {$j->mata_pelajaran}\n";
    echo "  Guru: " . ($guru ? $guru->nama : 'Unknown') . " (ID: {$j->guru_id})\n";
    echo "  Has Leave: $leaveStatus\n";
    echo "  Attendance Status: $attStatus\n";
    echo "\n";
}
