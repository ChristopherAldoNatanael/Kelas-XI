<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Schedule;
use App\Models\Teacher;

// Get Jonny teacher data
$jonny = Teacher::find(23);
echo "Guru: {$jonny->nama} (ID: {$jonny->id})\n";
echo "Status izin hari ini: ";

$leave = \App\Models\Leave::where('teacher_id', 23)
    ->where('start_date', '<=', now()->toDateString())
    ->where('end_date', '>=', now()->toDateString())
    ->where('status', 'approved')
    ->first();

if ($leave) {
    echo "YA - Alasan: {$leave->reason}\n";
} else {
    echo "TIDAK\n";
}

echo "\n";

// Create schedule for Jonny - Hari Rabu (today)
$schedule = Schedule::create([
    'hari' => 'Rabu',
    'kelas' => 'XI RPL 2',
    'mata_pelajaran' => 'Mancing',
    'guru_id' => 23,
    'jam_mulai' => '10:00:00',
    'jam_selesai' => '11:30:00',
    'ruang' => 'LAB 3'
]);

echo "✅ Jadwal berhasil dibuat:\n";
echo "   - ID: {$schedule->id}\n";
echo "   - Hari: {$schedule->hari}\n";
echo "   - Kelas: {$schedule->kelas}\n";
echo "   - Mata Pelajaran: {$schedule->mata_pelajaran}\n";
echo "   - Guru: {$jonny->nama}\n";
echo "   - Jam: {$schedule->jam_mulai} - {$schedule->jam_selesai}\n";
echo "   - Ruang: {$schedule->ruang}\n";

echo "\n";
echo "📌 Karena Jonny sedang IZIN hari ini, jadwal ini akan muncul dengan status 'Guru Izin' di aplikasi Android role Kurikulum!\n";
