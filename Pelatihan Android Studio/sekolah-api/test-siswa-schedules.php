<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

echo "=== TESTING SISWA SCHEDULE FILTERING ===\n\n";

// Find a siswa user
$siswa = User::where('role', 'siswa')
    ->whereNotNull('class_id')
    ->with('class')
    ->first();

if (!$siswa) {
    echo "ERROR: No siswa found with class_id!\n";
    exit(1);
}

echo "Found Siswa:\n";
echo "  ID: {$siswa->id}\n";
echo "  Name: {$siswa->nama}\n";
echo "  Email: {$siswa->email}\n";
echo "  Role: {$siswa->role}\n";
echo "  Class ID: {$siswa->class_id}\n";

if ($siswa->class) {
    echo "  Class Name: {$siswa->class->nama_kelas}\n";
    echo "  Class Code: {$siswa->class->kode_kelas}\n";
} else {
    echo "  ERROR: Class relationship not found!\n";
    exit(1);
}

echo "\n=== SCHEDULES FOR THIS CLASS ===\n\n";

$schedules = Schedule::where('kelas', $siswa->class->nama_kelas)
    ->with('guru:id,nama')
    ->orderBy('hari')
    ->orderBy('jam_mulai')
    ->get();

echo "Total schedules found: " . $schedules->count() . "\n\n";

if ($schedules->count() > 0) {
    foreach ($schedules as $schedule) {
        $guruName = $schedule->guru ? $schedule->guru->nama : 'N/A';
        echo "  [{$schedule->hari}] {$schedule->jam_mulai}-{$schedule->jam_selesai} | {$schedule->mata_pelajaran} | Guru: {$guruName}\n";
    }
} else {
    echo "  NO SCHEDULES FOUND FOR THIS CLASS!\n";
    echo "  This is the problem - schedules.kelas != classes.nama_kelas\n\n";

    // Let's check what kelas values exist in schedules
    echo "\n=== AVAILABLE KELAS VALUES IN SCHEDULES TABLE ===\n\n";
    $kelasValues = Schedule::distinct()->pluck('kelas')->sort();
    foreach ($kelasValues as $kelas) {
        echo "  - {$kelas}\n";
    }

    echo "\n=== CHECKING FOR SIMILAR NAMES ===\n\n";
    $similarSchedules = Schedule::where('kelas', 'like', '%' . $siswa->class->level . '%')
        ->orWhere('kelas', 'like', '%' . $siswa->class->major . '%')
        ->get();

    echo "Found {$similarSchedules->count()} schedules with similar class name:\n";
    foreach ($similarSchedules->take(5) as $s) {
        echo "  - {$s->kelas} | {$s->mata_pelajaran}\n";
    }
}

echo "\n=== ALL SISWA USERS ===\n\n";

$allSiswa = User::where('role', 'siswa')
    ->with('class')
    ->get();

echo "Total siswa: " . $allSiswa->count() . "\n\n";

foreach ($allSiswa->take(10) as $s) {
    $className = $s->class ? $s->class->nama_kelas : 'NO CLASS';
    $scheduleCount = $s->class ? Schedule::where('kelas', $s->class->nama_kelas)->count() : 0;
    echo "  {$s->nama} ({$s->email}) -> Class: {$className} -> Schedules: {$scheduleCount}\n";
}

echo "\n=== DONE ===\n";
