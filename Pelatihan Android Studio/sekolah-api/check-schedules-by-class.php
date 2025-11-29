<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

echo "=== SCHEDULES BY CLASS ===\n\n";

$schedulesByClass = Schedule::select('kelas', DB::raw('COUNT(*) as count'))
    ->groupBy('kelas')
    ->orderBy('kelas')
    ->get();

foreach ($schedulesByClass as $item) {
    echo "Kelas: {$item->kelas} -> {$item->count} jadwal\n";
}

echo "\n=== SAMPLE SCHEDULES (First 10) ===\n\n";

$samples = Schedule::with('guru:id,nama')
    ->orderBy('kelas')
    ->orderBy('hari')
    ->limit(10)
    ->get();

foreach ($samples as $schedule) {
    $guruName = $schedule->guru ? $schedule->guru->nama : 'N/A';
    echo "ID: {$schedule->id} | Kelas: {$schedule->kelas} | Hari: {$schedule->hari} | Mapel: {$schedule->mata_pelajaran} | Guru: {$guruName}\n";
}

echo "\n=== CLASSES IN DATABASE ===\n\n";

$classes = DB::table('classrooms')
    ->select('id', 'nama_kelas', 'kode_kelas')
    ->orderBy('nama_kelas')
    ->get();

foreach ($classes as $class) {
    echo "ID: {$class->id} | Nama: {$class->nama_kelas} | Kode: {$class->kode_kelas}\n";
}

echo "\n=== CHECKING CLASS NAME MATCHING ===\n\n";

// Check if schedule kelas matches classroom nama_kelas
$distinctScheduleClasses = Schedule::distinct()->pluck('kelas')->sort();
$distinctClassroomNames = DB::table('classrooms')->distinct()->pluck('nama_kelas')->sort();

echo "Schedule kelas values:\n";
foreach ($distinctScheduleClasses as $kelas) {
    echo "  - {$kelas}\n";
}

echo "\nClassroom nama_kelas values:\n";
foreach ($distinctClassroomNames as $nama) {
    echo "  - {$nama}\n";
}

echo "\n=== DONE ===\n";
