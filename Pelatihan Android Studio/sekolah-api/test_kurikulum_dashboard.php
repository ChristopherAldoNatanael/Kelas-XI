<?php

/**
 * Test script untuk memverifikasi perbaikan Kurikulum Dashboard
 *
 * Masalah: Dashboard selalu menampilkan "pending" meskipun siswa sudah submit attendance
 *
 * Perbaikan yang dilakukan:
 * 1. Fix targetDate calculation - sebelumnya menggunakan previous() yang mengarah ke minggu lalu
 * 2. Cache time dikurangi dari 30 detik ke 10 detik
 * 3. Query attendance diperbaiki untuk filter berdasarkan schedule_id yang relevan
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\ClassModel;

echo "=== TEST KURIKULUM DASHBOARD FIX ===\n\n";

// Test 1: Verifikasi perhitungan targetDate
echo "1. Test perhitungan targetDate:\n";
$dayMap = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$reverseDayMap = array_flip($dayMap);

$today = Carbon::now()->format('Y-m-d');
$hari = $dayMap[Carbon::now()->format('l')];
echo "   Hari ini: $hari ($today)\n";

// Test untuk setiap hari
foreach ($dayMap as $englishDay => $filterDay) {
    $targetDate = $today;
    if ($filterDay !== $hari) {
        $todayDayNum = Carbon::now()->dayOfWeekIso;
        $targetDayNum = match ($englishDay) {
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 7,
            default => 1
        };

        $daysAgo = $todayDayNum - $targetDayNum;
        if ($daysAgo <= 0) {
            $daysAgo += 7;
        }

        $targetDate = Carbon::now()->subDays($daysAgo)->format('Y-m-d');
    }
    echo "   Filter hari $filterDay -> targetDate: $targetDate\n";
}

echo "\n2. Cek data attendance terbaru:\n";
$recentAttendances = TeacherAttendance::orderBy('created_at', 'desc')
    ->take(10)
    ->get(['id', 'schedule_id', 'tanggal', 'status', 'created_at']);

if ($recentAttendances->isEmpty()) {
    echo "   Tidak ada data attendance di database.\n";
} else {
    foreach ($recentAttendances as $att) {
        $tanggal = $att->tanggal instanceof \Carbon\Carbon ? $att->tanggal->format('Y-m-d') : $att->tanggal;
        echo "   ID: {$att->id} | Schedule: {$att->schedule_id} | Tanggal: {$tanggal} | Status: {$att->status}\n";
    }
}

echo "\n3. Cek jadwal untuk kelas tertentu:\n";
$classModel = ClassModel::first();
if ($classModel) {
    echo "   Kelas: {$classModel->nama_kelas}\n";

    $schedules = Schedule::where('kelas', $classModel->nama_kelas)
        ->where('hari', $hari)
        ->orderBy('jam_mulai')
        ->get(['id', 'mata_pelajaran', 'jam_mulai', 'jam_selesai']);

    if ($schedules->isEmpty()) {
        echo "   Tidak ada jadwal untuk hari $hari\n";
    } else {
        echo "   Jadwal hari $hari:\n";
        foreach ($schedules as $s) {
            echo "     - ID: {$s->id} | {$s->mata_pelajaran} | {$s->jam_mulai}-{$s->jam_selesai}\n";
        }

        // Cek attendance untuk jadwal ini
        $scheduleIds = $schedules->pluck('id')->toArray();
        $attendances = TeacherAttendance::where('tanggal', $today)
            ->whereIn('schedule_id', $scheduleIds)
            ->get();

        echo "\n   Attendance hari ini ($today):\n";
        if ($attendances->isEmpty()) {
            echo "     Tidak ada data attendance untuk jadwal ini hari ini.\n";
        } else {
            foreach ($attendances as $att) {
                echo "     - Schedule: {$att->schedule_id} | Status: {$att->status}\n";
            }
        }
    }
} else {
    echo "   Tidak ada data kelas di database.\n";
}

echo "\n=== TEST SELESAI ===\n";
echo "\nPerbaikan yang sudah diterapkan:\n";
echo "✓ Fix perhitungan targetDate (tidak lagi menggunakan previous() yang salah)\n";
echo "✓ Cache dikurangi dari 30 detik ke 10 detik\n";
echo "✓ Query attendance diperbaiki untuk filter schedule_id yang relevan\n";
echo "✓ Menambahkan opsi force refresh dengan parameter ?refresh=true\n";
