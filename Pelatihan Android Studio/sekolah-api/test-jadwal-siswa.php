<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

// Clear cache
Cache::flush();
echo "Cache cleared\n\n";

// Cek siswa di kelas XI RPL 2
$user = \App\Models\User::where('class_id', 5)->first();
if ($user) {
    echo "User: {$user->nama}\n";
    echo "Class ID: {$user->class_id}\n";
    $class = $user->class;
    echo "Class Name: " . ($class ? $class->nama_kelas : 'N/A') . "\n";
} else {
    echo "No user found in class_id 5\n";
}

echo "\n--- Checking schedules for XI RPL 2 ---\n";

// Cek jadwal untuk kelas XI RPL 2
$schedules = DB::table('schedules')
    ->select(['id', 'hari', 'mata_pelajaran', 'jam_mulai', 'jam_selesai', 'guru_id', 'kelas'])
    ->where('kelas', 'XI RPL 2')
    ->get();

echo "Total schedules: " . $schedules->count() . "\n";

foreach ($schedules as $s) {
    $guru = DB::table('teachers')->where('id', $s->guru_id)->first();
    echo "- {$s->hari} {$s->jam_mulai}-{$s->jam_selesai}: {$s->mata_pelajaran} (Guru ID: {$s->guru_id} - " . ($guru ? $guru->nama : 'N/A') . ")\n";
}

echo "\n--- Checking leaves for today ---\n";

$today = now()->toDateString();
$leaves = DB::table('leaves')
    ->select(['teacher_id', 'reason', 'substitute_teacher_id', 'status', 'start_date', 'end_date'])
    ->where('status', 'approved')
    ->where('start_date', '<=', $today)
    ->where('end_date', '>=', $today)
    ->get();

echo "Approved leaves today ({$today}): " . $leaves->count() . "\n";

foreach ($leaves as $l) {
    $teacher = DB::table('teachers')->where('id', $l->teacher_id)->first();
    $substitute = $l->substitute_teacher_id ? DB::table('teachers')->where('id', $l->substitute_teacher_id)->first() : null;
    
    echo "- Teacher: " . ($teacher ? $teacher->nama : "ID {$l->teacher_id}") . "\n";
    echo "  Reason: {$l->reason}\n";
    echo "  Substitute: " . ($substitute ? $substitute->nama : "Not assigned (ID: {$l->substitute_teacher_id})") . "\n";
    echo "\n";
}

echo "\n--- Simulating API response ---\n";

// Get the class name
$className = 'XI RPL 2';

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
    ->where('schedules.kelas', $className)
    ->orderByRaw("FIELD(schedules.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
    ->orderBy('schedules.jam_mulai')
    ->get();

// Get teacher IDs
$teacherIds = $schedules->pluck('guru_id')->filter()->unique()->toArray();

// Get approved leaves
$teachersOnLeave = DB::table('leaves')
    ->select(['teacher_id', 'reason', 'custom_reason', 'substitute_teacher_id'])
    ->where('status', 'approved')
    ->where('start_date', '<=', $today)
    ->where('end_date', '>=', $today)
    ->whereIn('teacher_id', $teacherIds)
    ->get()
    ->keyBy('teacher_id');

echo "Teachers on leave in this class: " . $teachersOnLeave->count() . "\n";

// Get substitute names
$substituteIds = $teachersOnLeave->pluck('substitute_teacher_id')->filter()->toArray();
$substituteTeachers = [];
if (!empty($substituteIds)) {
    $substituteTeachers = DB::table('teachers')
        ->whereIn('id', $substituteIds)
        ->pluck('nama', 'id')
        ->toArray();
}

echo "\nSubstitute teachers: " . json_encode($substituteTeachers) . "\n";

$todayDayName = now()->format('l');
$todayDayIndonesian = match(strtolower($todayDayName)) {
    'monday' => 'Senin',
    'tuesday' => 'Selasa',
    'wednesday' => 'Rabu',
    'thursday' => 'Kamis',
    'friday' => 'Jumat',
    'saturday' => 'Sabtu',
    'sunday' => 'Minggu',
    default => $todayDayName
};

echo "\nToday is: {$todayDayIndonesian}\n";
echo "\n--- Schedule items for today with leave status ---\n";

foreach ($schedules as $item) {
    if (strtolower($item->hari) !== strtolower($todayDayIndonesian)) continue;
    
    $teacherLeave = $teachersOnLeave->get($item->guru_id);
    
    echo "\n{$item->jam_mulai}-{$item->jam_selesai}: {$item->mata_pelajaran}\n";
    echo "  Teacher: {$item->guru_nama} (ID: {$item->guru_id})\n";
    
    if ($teacherLeave) {
        echo "  STATUS: IZIN ({$teacherLeave->reason})\n";
        if ($teacherLeave->substitute_teacher_id) {
            $subName = $substituteTeachers[$teacherLeave->substitute_teacher_id] ?? "ID {$teacherLeave->substitute_teacher_id}";
            echo "  Substitute: {$subName}\n";
        } else {
            echo "  Substitute: NOT ASSIGNED\n";
        }
    } else {
        echo "  STATUS: Normal (not on leave)\n";
    }
}

echo "\n\nDone!\n";
