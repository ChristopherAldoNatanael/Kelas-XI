<?php
// Test script untuk query jadwal

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Get siswa user
    $user = App\Models\User::where('role', 'siswa')->first();
    echo "User: " . $user->email . "\n";
    echo "Class ID: " . $user->class_id . "\n";

    // Get class
    $class = DB::table('classes')->where('id', $user->class_id)->first();
    echo "Class Name: " . $class->nama_kelas . "\n";

    // Get schedules
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
        ->where('schedules.kelas', $class->nama_kelas)
        ->orderBy('schedules.hari')
        ->orderBy('schedules.jam_mulai')
        ->limit(5)
        ->get();

    echo "Schedules found: " . $schedules->count() . "\n\n";

    foreach ($schedules as $s) {
        echo "- {$s->hari}: {$s->mata_pelajaran} ({$s->jam_mulai}-{$s->jam_selesai}) - {$s->guru_nama}\n";
    }

    echo "\n=== SUCCESS ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
