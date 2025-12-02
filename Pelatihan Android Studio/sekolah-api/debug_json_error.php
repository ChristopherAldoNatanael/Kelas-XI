<?php

/**
 * Debug script untuk mencari penyebab JSON parsing error
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TeacherAttendance;
use App\Models\Schedule;
use App\Models\Teacher;
use Carbon\Carbon;

$targetDate = Carbon::now()->format('Y-m-d');
$dayOfWeek = Carbon::now()->dayOfWeek;
$days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hari = $days[$dayOfWeek];

echo "Today: $targetDate ($hari)\n\n";

// Check for teachers with null NIP
echo "=== Teachers with NULL or empty NIP ===\n";
$teachersWithNullNip = Teacher::whereNull('nip')->orWhere('nip', '')->get();
foreach ($teachersWithNullNip as $t) {
    echo "Teacher ID: {$t->id}, Nama: {$t->nama}, NIP: '" . ($t->nip ?? 'NULL') . "'\n";
}

if (count($teachersWithNullNip) == 0) {
    echo "None found.\n";
}

// Get schedules for today
echo "\n=== Schedules for today with teacher data ===\n";
$schedules = Schedule::with(['kelas', 'guru', 'mapel'])
    ->where('hari', $hari)
    ->get();

echo "Found " . count($schedules) . " schedules for $hari\n\n";

foreach ($schedules as $s) {
    $teacher = $s->guru;
    $className = $s->kelas ? $s->kelas->name : 'NULL';
    $mapelName = $s->mapel ? $s->mapel->nama_mapel : 'NULL';

    if ($teacher) {
        $nip = $teacher->nip;
        if ($nip === null || $nip === '') {
            echo "⚠ Schedule #{$s->id}: Class=$className, Mapel=$mapelName\n";
            echo "   Teacher: {$teacher->nama} (ID: {$teacher->id}), NIP = '" . ($nip ?? 'NULL') . "'\n";
        }
    } else {
        echo "⚠ Schedule #{$s->id}: Class=$className, Mapel=$mapelName - NO TEACHER!\n";
    }
}

// Check class management data structure
echo "\n=== Testing Class Management Query ===\n";

$attendances = TeacherAttendance::with(['schedule.kelas', 'schedule.mapel', 'guru', 'guruAsli'])
    ->whereDate('tanggal', $targetDate)
    ->whereIn('status', ['pending', 'tidak_hadir', 'izin', 'telat'])
    ->get();

echo "Found " . count($attendances) . " attendances with issues\n\n";

foreach ($attendances as $att) {
    echo "Attendance #{$att->id}: Status={$att->status}\n";

    if ($att->guru) {
        echo "   Guru NIP: '" . ($att->guru->nip ?? 'NULL') . "'\n";
        echo "   Guru Nama: '" . ($att->guru->nama ?? 'NULL') . "'\n";
    } else {
        echo "   Guru: NULL\n";
    }

    if ($att->schedule && $att->schedule->kelas) {
        echo "   Kelas: " . $att->schedule->kelas->name . "\n";
    }
}

echo "\n=== DONE ===\n";
