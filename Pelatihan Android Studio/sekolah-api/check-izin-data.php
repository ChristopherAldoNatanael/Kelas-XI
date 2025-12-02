<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LEAVES TABLE ===" . PHP_EOL;
$leaves = DB::table('leaves')->get();
echo 'Total leaves: ' . count($leaves) . PHP_EOL;
foreach ($leaves as $leave) {
    echo "ID: {$leave->id}, Teacher: {$leave->teacher_id}, Status: {$leave->status}, Reason: {$leave->reason}, Start: {$leave->start_date}, End: {$leave->end_date}" . PHP_EOL;
}

echo PHP_EOL . "=== TEACHER ATTENDANCES WITH STATUS IZIN ===" . PHP_EOL;
$izinAttendances = DB::table('teacher_attendances')->where('status', 'izin')->get();
echo 'Total izin attendances: ' . count($izinAttendances) . PHP_EOL;
foreach ($izinAttendances as $att) {
    echo "ID: {$att->id}, Guru: {$att->guru_id}, Date: {$att->tanggal}, Status: {$att->status}" . PHP_EOL;
}

echo PHP_EOL . "=== ALL TEACHER ATTENDANCES STATUS SUMMARY ===" . PHP_EOL;
$summary = DB::table('teacher_attendances')
    ->select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();
foreach ($summary as $s) {
    echo "Status: {$s->status}, Count: {$s->count}" . PHP_EOL;
}

echo PHP_EOL . "=== TEACHER ATTENDANCES TABLE STRUCTURE ===" . PHP_EOL;
$columns = DB::select("SHOW COLUMNS FROM teacher_attendances");
foreach ($columns as $col) {
    echo "Column: {$col->Field}, Type: {$col->Type}" . PHP_EOL;
}
