<?php

/**
 * Debug script untuk memeriksa jadwal X RPL 2 dan guru yang izin
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Leave;
use App\Models\TeacherAttendance;

Carbon::setLocale('id');

$today = Carbon::now();
$todayStr = $today->format('Y-m-d');
$dayName = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
][$today->dayOfWeekIso];

echo "=== DEBUG: Jadwal X RPL 2 dan Guru Izin ===\n\n";
echo "Tanggal: $todayStr\n";
echo "Hari: $dayName\n\n";

// 1. Ambil semua jadwal X RPL 2 untuk hari ini
echo "=== 1. JADWAL X RPL 2 HARI INI ($dayName) ===\n\n";

$schedules = Schedule::where('kelas', 'X RPL 2')
    ->where('hari', $dayName)
    ->orderBy('jam_mulai')
    ->get();

echo "Jumlah jadwal: " . $schedules->count() . "\n\n";

foreach ($schedules as $schedule) {
    $teacher = Teacher::find($schedule->guru_id);
    echo "ID: {$schedule->id}\n";
    echo "  Guru ID: {$schedule->guru_id} - " . ($teacher->nama ?? 'Unknown') . "\n";
    echo "  Mapel: {$schedule->mata_pelajaran}\n";
    echo "  Jam: {$schedule->jam_mulai} - {$schedule->jam_selesai}\n";
    echo "\n";
}

// 2. Cek semua approved leaves yang aktif hari ini
echo "=== 2. SEMUA APPROVED LEAVES AKTIF HARI INI ===\n\n";

$activeLeaves = Leave::where('status', 'approved')
    ->where('start_date', '<=', $todayStr)
    ->where('end_date', '>=', $todayStr)
    ->get();

echo "Jumlah leaves aktif: " . $activeLeaves->count() . "\n\n";

foreach ($activeLeaves as $leave) {
    $teacher = Teacher::find($leave->teacher_id);
    echo "Leave ID: {$leave->id}\n";
    echo "  Teacher ID: {$leave->teacher_id} - " . ($teacher->nama ?? 'Unknown') . "\n";
    echo "  Tanggal: {$leave->start_date} s/d {$leave->end_date}\n";
    echo "  Alasan: {$leave->reason}\n";
    echo "  Status: {$leave->status}\n";

    // Cek apakah guru ini punya jadwal di X RPL 2 hari ini
    $teacherSchedules = Schedule::where('guru_id', $leave->teacher_id)
        ->where('kelas', 'X RPL 2')
        ->where('hari', $dayName)
        ->get();

    echo "  Jadwal di X RPL 2 hari ini: " . $teacherSchedules->count() . "\n";
    foreach ($teacherSchedules as $ts) {
        echo "    - {$ts->mata_pelajaran} ({$ts->jam_mulai} - {$ts->jam_selesai})\n";
    }
    echo "\n";
}

// 3. Cek semua leaves (termasuk pending)
echo "=== 3. SEMUA LEAVES (SEMUA STATUS) ===\n\n";

$allLeaves = Leave::orderBy('created_at', 'desc')->get();

foreach ($allLeaves as $leave) {
    $teacher = Teacher::find($leave->teacher_id);
    echo "ID: {$leave->id} | Teacher: " . ($teacher->nama ?? 'Unknown');
    echo " | {$leave->start_date} - {$leave->end_date}";
    echo " | Status: {$leave->status}";
    echo " | Reason: {$leave->reason}\n";
}

// 4. Cek attendance untuk jadwal X RPL 2 hari ini
echo "\n=== 4. ATTENDANCE X RPL 2 HARI INI ===\n\n";

$scheduleIds = $schedules->pluck('id')->toArray();
$attendances = TeacherAttendance::whereIn('schedule_id', $scheduleIds)
    ->where('tanggal', $todayStr)
    ->get();

echo "Jumlah attendance records: " . $attendances->count() . "\n\n";

foreach ($attendances as $att) {
    $schedule = Schedule::find($att->schedule_id);
    echo "Attendance ID: {$att->id}\n";
    echo "  Schedule ID: {$att->schedule_id}\n";
    echo "  Mapel: " . ($schedule->mata_pelajaran ?? 'Unknown') . "\n";
    echo "  Status: {$att->status}\n";
    echo "  Guru ID: {$att->guru_id}\n";
    echo "  Guru Asli ID: {$att->guru_asli_id}\n";
    echo "  Guru Pengganti ID: {$att->guru_pengganti_id}\n";
    echo "\n";
}

// 5. Tampilkan guru mana yang seharusnya izin di X RPL 2
echo "=== 5. GURU YANG SEHARUSNYA IZIN DI X RPL 2 HARI INI ===\n\n";

$guruIzin = [];
foreach ($activeLeaves as $leave) {
    $teacherSchedules = Schedule::where('guru_id', $leave->teacher_id)
        ->where('kelas', 'X RPL 2')
        ->where('hari', $dayName)
        ->get();

    foreach ($teacherSchedules as $ts) {
        $teacher = Teacher::find($leave->teacher_id);
        $guruIzin[] = [
            'teacher_id' => $leave->teacher_id,
            'teacher_name' => $teacher->nama ?? 'Unknown',
            'schedule_id' => $ts->id,
            'mapel' => $ts->mata_pelajaran,
            'jam' => $ts->jam_mulai . ' - ' . $ts->jam_selesai,
            'leave_reason' => $leave->reason,
            'substitute_id' => $leave->substitute_teacher_id
        ];
    }
}

echo "Guru izin dengan jadwal di X RPL 2: " . count($guruIzin) . "\n\n";

foreach ($guruIzin as $gi) {
    echo "- {$gi['teacher_name']} (ID: {$gi['teacher_id']})\n";
    echo "  Mapel: {$gi['mapel']}\n";
    echo "  Jam: {$gi['jam']}\n";
    echo "  Alasan: {$gi['leave_reason']}\n";
    echo "  Pengganti ID: " . ($gi['substitute_id'] ?? 'Tidak ada') . "\n";
    echo "\n";
}
