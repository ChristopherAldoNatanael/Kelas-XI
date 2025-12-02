<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING teacher_attendances DATA ===" . PHP_EOL;

$records = DB::table('teacher_attendances')
    ->select('id', 'tanggal', 'jam_masuk', 'schedule_id', 'status')
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get();

foreach ($records as $r) {
    echo "ID: {$r->id} | tanggal: {$r->tanggal} | jam_masuk: {$r->jam_masuk} | status: {$r->status}" . PHP_EOL;
}

echo PHP_EOL . "=== CHECKING FOR BAD DATA (jam_masuk contains date) ===" . PHP_EOL;

$badRecords = DB::table('teacher_attendances')
    ->select('id', 'tanggal', 'jam_masuk', 'schedule_id')
    ->whereNotNull('jam_masuk')
    ->where('jam_masuk', 'like', '20%')
    ->get();

echo "Bad records count: " . $badRecords->count() . PHP_EOL;
foreach ($badRecords as $r) {
    echo "ID: {$r->id} | tanggal: {$r->tanggal} | jam_masuk: {$r->jam_masuk}" . PHP_EOL;
}
