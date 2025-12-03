<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;

$today = Carbon::now('Asia/Jakarta');
$dayOfWeek = $today->dayOfWeekIso;
$days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$dayName = $days[$dayOfWeek];
$dateStr = $today->format('Y-m-d');

echo "===========================================\n";
echo "DEBUG: Kehadiran Guru Hari Ini\n";
echo "===========================================\n";
echo "Hari ini: $dayName ($dateStr)\n\n";

// Cek jadwal hari ini
$schedules = \App\Models\Schedule::where('hari', $dayName)->with('teacher')->get();
echo "Total jadwal hari ini: " . $schedules->count() . " schedule entries\n";

// Distinct teachers
$teacherIds = $schedules->pluck('guru_id')->unique()->filter()->values()->toArray();
echo "Guru yang punya jadwal: " . count($teacherIds) . " guru\n\n";

echo "Detail per guru:\n";
echo "-------------------------------------------\n";

foreach ($teacherIds as $tid) {
    $teacher = \App\Models\Teacher::find($tid);
    $name = $teacher ? $teacher->nama : 'Unknown';
    $scheduleCount = $schedules->where('guru_id', $tid)->count();
    echo "[ Guru ID: $tid ] $name\n";
    echo "   Jadwal hari ini: $scheduleCount jadwal\n";

    // Check attendance
    $attendances = \App\Models\TeacherAttendance::where('guru_id', $tid)->where('tanggal', $dateStr)->get();
    if ($attendances->count() > 0) {
        echo "   Attendance records: " . $attendances->count() . "\n";
        foreach ($attendances as $att) {
            echo "     - Status: {$att->status}, Waktu: {$att->waktu_hadir}\n";
        }
    } else {
        echo "   Attendance: ❌ BELUM CHECK-IN\n";
    }

    // Check leave
    $leave = \App\Models\Leave::where('teacher_id', $tid)
        ->where('status', 'approved')
        ->where('start_date', '<=', $dateStr)
        ->where('end_date', '>=', $dateStr)
        ->first();
    if ($leave) {
        echo "   Leave: ✅ APPROVED ({$leave->reason})\n";
    }

    echo "\n";
}

// Summary
echo "===========================================\n";
echo "Kesimpulan:\n";
echo "- Total guru terjadwal: " . count($teacherIds) . "\n";

$presentCount = 0;
$lateCount = 0;
$absentCount = 0;
$onLeaveCount = 0;
$pendingCount = 0;

foreach ($teacherIds as $tid) {
    // Check leave first
    $hasLeave = \App\Models\Leave::where('teacher_id', $tid)
        ->where('status', 'approved')
        ->where('start_date', '<=', $dateStr)
        ->where('end_date', '>=', $dateStr)
        ->exists();

    if ($hasLeave) {
        $onLeaveCount++;
        continue;
    }

    // Check attendance
    $attendances = \App\Models\TeacherAttendance::where('guru_id', $tid)->where('tanggal', $dateStr)->get();

    if ($attendances->isEmpty()) {
        $pendingCount++;
        continue;
    }

    $statuses = $attendances->pluck('status')->unique()->toArray();

    if (in_array('hadir', $statuses)) {
        $presentCount++;
    } elseif (in_array('telat', $statuses)) {
        $lateCount++;
    } elseif (in_array('tidak_hadir', $statuses)) {
        $absentCount++;
    } elseif (in_array('izin', $statuses) || in_array('diganti', $statuses)) {
        $onLeaveCount++;
    } else {
        $pendingCount++;
    }
}

echo "- Hadir: $presentCount\n";
echo "- Terlambat: $lateCount\n";
echo "- Tidak Hadir: $absentCount\n";
echo "- Izin: $onLeaveCount\n";
echo "- Pending: $pendingCount\n";
echo "===========================================\n";
