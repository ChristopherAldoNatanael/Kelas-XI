<?php
// Test endpoint jadwal-siswa
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Ambil token siswa X RPL 1
$siswa = DB::table('users')->where('class_id', 1)->first();
if (!$siswa) {
    echo "Tidak ada siswa dengan class_id=1\n";
    exit;
}

echo "Siswa: {$siswa->name} (ID: {$siswa->id})\n";

// Ambil token
$tokenRecord = DB::table('personal_access_tokens')
    ->where('tokenable_id', $siswa->id)
    ->where('tokenable_type', 'App\\Models\\User')
    ->first();

if (!$tokenRecord) {
    echo "Tidak ada token untuk siswa ini\n";
    exit;
}

echo "Token ID: {$tokenRecord->id}\n";

// Langsung query jadwal seperti di controller
$classId = $siswa->class_id;
$userClass = DB::table('classes')->where('id', $classId)->first();
$className = $userClass->nama_kelas;

echo "Kelas: {$className}\n\n";

$schedules = DB::table('schedules')
    ->select([
        'schedules.id',
        'schedules.hari',
        'schedules.mata_pelajaran',
        'schedules.jam_mulai',
        'schedules.jam_selesai',
        'schedules.guru_id',
        'teachers.nama as guru_nama'
    ])
    ->leftJoin('teachers', 'schedules.guru_id', '=', 'teachers.id')
    ->where('schedules.kelas', $className)
    ->orderByRaw("FIELD(schedules.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
    ->orderBy('schedules.jam_mulai')
    ->get();

echo "Total jadwal: " . count($schedules) . "\n\n";

foreach ($schedules as $s) {
    echo "{$s->hari} | {$s->jam_mulai} - {$s->jam_selesai} | {$s->mata_pelajaran} | {$s->guru_nama}\n";
}

// Test format JSON
echo "\n=== JSON OUTPUT ===\n";
$formattedData = [];
foreach ($schedules as $item) {
    $jamMulai = $item->jam_mulai;
    $jamSelesai = $item->jam_selesai;
    
    if ($jamMulai && strlen($jamMulai) == 5) {
        $jamMulai .= ':00';
    }
    if ($jamSelesai && strlen($jamSelesai) == 5) {
        $jamSelesai .= ':00';
    }
    
    $formattedData[] = [
        'id' => (int) $item->id,
        'class_id' => (int) $classId,
        'subject_id' => 0,
        'teacher_id' => (int) ($item->guru_id ?? 0),
        'day_of_week' => $item->hari ?? '',
        'period' => 1,
        'start_time' => $jamMulai ?? '',
        'end_time' => $jamSelesai ?? '',
        'status' => 'active',
        'class_name' => $className,
        'subject_name' => $item->mata_pelajaran ?? '',
        'teacher_name' => $item->guru_nama ?? ''
    ];
}

$response = [
    'success' => true,
    'message' => 'Jadwal mingguan berhasil dimuat (' . count($formattedData) . ' jadwal)',
    'data' => $formattedData
];

$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo $json;
echo "\n\nJSON Length: " . strlen($json) . " bytes\n";
