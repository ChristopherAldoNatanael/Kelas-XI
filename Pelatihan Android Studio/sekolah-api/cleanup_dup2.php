<?php
// Hapus jadwal duplikat berdasarkan kelas + hari + jam_mulai
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$duplicates = DB::table('schedules')
    ->select('kelas', 'hari', 'jam_mulai', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(id) as keep_id'))
    ->groupBy('kelas', 'hari', 'jam_mulai')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo "Duplikat berdasarkan kelas+hari+jam: " . count($duplicates) . "\n";

foreach ($duplicates as $dup) {
    echo "Duplikat: {$dup->kelas} | {$dup->hari} | {$dup->jam_mulai} ({$dup->cnt} records)\n";
    
    $deleted = DB::table('schedules')
        ->where('kelas', $dup->kelas)
        ->where('hari', $dup->hari)
        ->where('jam_mulai', $dup->jam_mulai)
        ->where('id', '!=', $dup->keep_id)
        ->delete();
    
    echo "  -> Dihapus: $deleted\n";
}

echo "\nStatistik baru:\n";
$stats = DB::table('schedules')->select('kelas', DB::raw('count(*) as total'))->groupBy('kelas')->get();
foreach($stats as $s) { 
    echo "{$s->kelas}: {$s->total}\n"; 
}
