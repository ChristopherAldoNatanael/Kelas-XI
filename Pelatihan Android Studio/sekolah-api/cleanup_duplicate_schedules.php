<?php
// Script untuk menghapus jadwal duplikat
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MEMBERSIHKAN JADWAL DUPLIKAT ===\n\n";

// Cari duplikat berdasarkan kelas + hari + jam_mulai + mata_pelajaran
$duplicates = DB::table('schedules')
    ->select('kelas', 'hari', 'jam_mulai', 'mata_pelajaran', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(id) as keep_id'))
    ->groupBy('kelas', 'hari', 'jam_mulai', 'mata_pelajaran')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo "Menemukan " . count($duplicates) . " grup duplikat\n\n";

$totalDeleted = 0;
foreach ($duplicates as $dup) {
    echo "Duplikat: {$dup->kelas} | {$dup->hari} | {$dup->jam_mulai} | {$dup->mata_pelajaran} ({$dup->cnt} records)\n";
    
    // Hapus semua kecuali yang ID paling besar (keep_id)
    $deleted = DB::table('schedules')
        ->where('kelas', $dup->kelas)
        ->where('hari', $dup->hari)
        ->where('jam_mulai', $dup->jam_mulai)
        ->where('mata_pelajaran', $dup->mata_pelajaran)
        ->where('id', '!=', $dup->keep_id)
        ->delete();
    
    echo "  -> Dihapus: $deleted\n";
    $totalDeleted += $deleted;
}

echo "\n=== TOTAL DIHAPUS: $totalDeleted ===\n";

// Tampilkan statistik baru
echo "\n=== STATISTIK BARU ===\n";
$stats = DB::table('schedules')
    ->select('kelas', DB::raw('count(*) as total'))
    ->groupBy('kelas')
    ->get();

foreach($stats as $s) {
    echo "{$s->kelas}: {$s->total} jadwal\n";
}
