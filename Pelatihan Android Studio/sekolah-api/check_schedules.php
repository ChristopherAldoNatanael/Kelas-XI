<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEACHER_ATTENDANCES TABLE STRUCTURE ===\n";
$cols = DB::select('DESCRIBE teacher_attendances');
foreach ($cols as $c) {
    echo $c->Field . ' | ' . $c->Type . "\n";
}

echo "\n=== SAMPLE ATTENDANCES ===\n";
$attendances = DB::table('teacher_attendances')->limit(5)->get();
foreach ($attendances as $a) {
    print_r($a);
}

echo "=== SCHEDULES FOR XI RPL 1 ===\n";
$schedules = DB::table('schedules')->where('kelas', 'XI RPL 1')->get();

if ($schedules->isEmpty()) {
    echo "No schedules found for XI RPL 1\n";
} else {
    foreach ($schedules as $schedule) {
        echo "{$schedule->hari} - {$schedule->mata_pelajaran} - Guru ID: {$schedule->guru_id}\n";
    }
}

echo "\n=== ALL SCHEDULES ===\n";
$allSchedules = DB::table('schedules')->get();

if ($allSchedules->isEmpty()) {
    echo "No schedules found in database\n";
} else {
    foreach ($allSchedules as $schedule) {
        echo "{$schedule->id}: {$schedule->hari} - {$schedule->kelas} - {$schedule->mata_pelajaran}\n";
    }
}

echo "\n=== TEACHERS ===\n";
$teachers = DB::table('teachers')->get();

if ($teachers->isEmpty()) {
    echo "No teachers found\n";
} else {
    foreach ($teachers as $teacher) {
        echo "{$teacher->id}: {$teacher->nama} (NIP: {$teacher->nip})\n";
    }
}
