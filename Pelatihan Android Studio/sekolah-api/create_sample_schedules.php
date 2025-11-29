<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CREATING SAMPLE SCHEDULES FOR X RPL 1 ===\n";

// Sample schedules for X RPL 1 (class_id = 1)
$schedules = [
    [
        'hari' => 'Senin',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Matematika Dasar',
        'guru_id' => 3, // Budi Santoso
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
        'ruang' => 'Lab Komputer 1'
    ],
    [
        'hari' => 'Senin',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Bahasa Indonesia',
        'guru_id' => 4, // Siti Nurhaliza
        'jam_mulai' => '08:45:00',
        'jam_selesai' => '10:15:00',
        'ruang' => 'Ruang Bahasa'
    ],
    [
        'hari' => 'Selasa',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Algoritma dan Pemrograman Dasar',
        'guru_id' => 7, // Rizki Ramadhan
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '09:30:00',
        'ruang' => 'Lab Komputer 2'
    ],
    [
        'hari' => 'Rabu',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Fisika',
        'guru_id' => 6, // Maya Sari
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '08:30:00',
        'ruang' => 'Lab Fisika'
    ],
    [
        'hari' => 'Kamis',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Bahasa Inggris',
        'guru_id' => 5, // Adi Wijaya
        'jam_mulai' => '08:45:00',
        'jam_selesai' => '10:15:00',
        'ruang' => 'Ruang Bahasa'
    ],
    [
        'hari' => 'Jumat',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Struktur Data',
        'guru_id' => 7, // Rizki Ramadhan
        'jam_mulai' => '07:00:00',
        'jam_selesai' => '09:30:00',
        'ruang' => 'Lab Komputer 1'
    ],
    [
        'hari' => 'Sabtu',
        'kelas' => 'X RPL 1',
        'mata_pelajaran' => 'Pemrograman Berorientasi Objek',
        'guru_id' => 9, // Eko Prasetyo
        'jam_mulai' => '08:00:00',
        'jam_selesai' => '10:30:00',
        'ruang' => 'Lab Komputer 2'
    ]
];

$inserted = 0;
foreach ($schedules as $schedule) {
    $existing = DB::table('schedules')
        ->where('hari', $schedule['hari'])
        ->where('kelas', $schedule['kelas'])
        ->where('mata_pelajaran', $schedule['mata_pelajaran'])
        ->first();

    if (!$existing) {
        DB::table('schedules')->insert($schedule);
        echo "✓ Inserted: {$schedule['hari']} - {$schedule['mata_pelajaran']}\n";
        $inserted++;
    } else {
        echo "⚠ Skipped (exists): {$schedule['hari']} - {$schedule['mata_pelajaran']}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Schedules inserted: $inserted\n";

$totalSchedules = DB::table('schedules')->where('kelas', 'X RPL 1')->count();
echo "Total schedules for X RPL 1: $totalSchedules\n";

echo "\n=== ALL SCHEDULES FOR X RPL 1 ===\n";
$xRpl1Schedules = DB::table('schedules')->where('kelas', 'X RPL 1')->get();
foreach ($xRpl1Schedules as $schedule) {
    echo "{$schedule->hari} - {$schedule->mata_pelajaran} ({$schedule->jam_mulai} - {$schedule->jam_selesai})\n";
}
