<?php
// Check schedule classes
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== JADWAL LENGKAP X RPL 1 ===\n";
$schedules = DB::table('schedules')
    ->where('kelas', 'X RPL 1')
    ->orderBy('hari')
    ->orderBy('jam_mulai')
    ->get();

echo "Total: " . count($schedules) . " jadwal\n\n";
foreach ($schedules as $s) {
    echo "{$s->hari} | {$s->jam_mulai} - {$s->jam_selesai} | {$s->mata_pelajaran}\n";
}

echo "\n=== SCHEDULES BY CLASS ===\n";
$schedules = DB::table('schedules')
    ->select('kelas', DB::raw('count(*) as total'))
    ->groupBy('kelas')
    ->get();

foreach ($schedules as $s) {
    echo "Kelas: '{$s->kelas}' => {$s->total} jadwal\n";
}

echo "\n=== CLASSES IN DATABASE ===\n";
$classes = DB::table('classes')->select('id', 'nama_kelas')->get();
foreach ($classes as $c) {
    echo "ID: {$c->id}, Nama: '{$c->nama_kelas}'\n";
}

echo "\n=== SISWA USERS ===\n";
$users = DB::table('users')
    ->where('role', 'siswa')
    ->select('id', 'name', 'class_id')
    ->limit(10)
    ->get();

foreach ($users as $u) {
    $className = $u->class_id ? DB::table('classes')->where('id', $u->class_id)->value('nama_kelas') : 'NO CLASS';
    echo "ID: {$u->id}, Nama: {$u->name}, class_id: " . ($u->class_id ?? 'NULL') . ", Kelas: {$className}\n";
}
