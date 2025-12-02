<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Creating Pending Attendance Data ===\n\n";

$tanggalHariIni = date('Y-m-d');
$dayMap = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hariIni = $dayMap[date('l')] ?? date('l');

echo "Tanggal: $tanggalHariIni ($hariIni)\n\n";

// Get schedules for today
$schedules = DB::select("
    SELECT s.id, s.kelas, s.mata_pelajaran, s.guru_id, s.jam_mulai, s.jam_selesai,
           COALESCE(t.nama, 'Unknown') as teacher_name
    FROM schedules s
    LEFT JOIN teachers t ON s.guru_id = t.id
    WHERE s.hari = ?
    ORDER BY s.jam_mulai
    LIMIT 10
", [$hariIni]);

echo "Found " . count($schedules) . " schedules for today\n\n";

if (count($schedules) == 0) {
    echo "No schedules found for $hariIni. Let's check all available days:\n";
    $days = DB::select("SELECT DISTINCT hari FROM schedules");
    foreach ($days as $day) {
        echo "  - " . $day->hari . "\n";
    }

    // Use first available day
    if (!empty($days)) {
        $hariIni = $days[0]->hari;
        echo "\nUsing: $hariIni\n";

        $schedules = DB::select("
            SELECT s.id, s.kelas, s.mata_pelajaran, s.guru_id, s.jam_mulai, s.jam_selesai,
                   COALESCE(t.nama, 'Unknown') as teacher_name
            FROM schedules s
            LEFT JOIN teachers t ON s.guru_id = t.id
            WHERE s.hari = ?
            ORDER BY s.jam_mulai
            LIMIT 10
        ", [$hariIni]);
        echo "Found " . count($schedules) . " schedules\n\n";
    }
}

// Delete existing attendance for today
$deleted = DB::delete("DELETE FROM teacher_attendances WHERE tanggal = ?", [$tanggalHariIni]);
echo "Deleted $deleted existing attendance records\n\n";

// Create various status data
$statuses = [
    ['status' => 'pending', 'keterangan' => 'Siswa melaporkan: Hadir - Guru sudah masuk kelas'],
    ['status' => 'pending', 'keterangan' => 'Siswa melaporkan: Telat - Guru datang terlambat 10 menit'],
    ['status' => 'pending', 'keterangan' => 'Siswa melaporkan: Hadir'],
    ['status' => 'tidak_hadir', 'keterangan' => 'Guru tidak masuk, perlu guru pengganti'],
    ['status' => 'tidak_hadir', 'keterangan' => 'Guru belum datang'],
];

$created = 0;
foreach ($schedules as $index => $schedule) {
    $statusData = $statuses[$index % count($statuses)];
    $jamMasuk = in_array($statusData['status'], ['pending']) ? date('H:i:s') : null;

    DB::insert("
        INSERT INTO teacher_attendances
        (schedule_id, guru_id, tanggal, jam_masuk, status, keterangan, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ", [
        $schedule->id,
        $schedule->guru_id,
        $tanggalHariIni,
        $jamMasuk,
        $statusData['status'],
        $statusData['keterangan']
    ]);

    $created++;
    echo "Created: {$schedule->teacher_name} - {$schedule->kelas} - {$schedule->mata_pelajaran}\n";
    echo "         Status: {$statusData['status']}\n\n";
}

echo "=== Created $created attendance records ===\n\n";

// Show summary
$summary = DB::select("
    SELECT status, COUNT(*) as count
    FROM teacher_attendances
    WHERE tanggal = ?
    GROUP BY status
", [$tanggalHariIni]);

echo "Summary:\n";
foreach ($summary as $s) {
    echo "  - {$s->status}: {$s->count}\n";
}
