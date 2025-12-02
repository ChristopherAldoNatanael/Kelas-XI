<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$today = Carbon::now();
$startOfWeek = $today->copy()->startOfWeek();
$endOfWeek = $startOfWeek->copy()->endOfWeek();

echo "=== CHECKING TEACHER 7 (RIZKI RAMADHAN) SCHEDULES ===" . PHP_EOL;
$schedules = DB::table('schedules')->where('guru_id', 7)->get();
echo "Total schedules for guru_id=7: " . count($schedules) . PHP_EOL;
foreach ($schedules as $s) {
    echo "ID: {$s->id}, Hari: {$s->hari}, Kelas: {$s->kelas}, Mapel: {$s->mata_pelajaran}" . PHP_EOL;
}

echo PHP_EOL . "=== ALL SCHEDULES ===" . PHP_EOL;
$allSchedules = DB::table('schedules')->get();
echo "Total schedules: " . count($allSchedules) . PHP_EOL;
foreach ($allSchedules as $s) {
    $teacher = DB::table('teachers')->find($s->guru_id);
    echo "ID: {$s->id}, Hari: {$s->hari}, Kelas: {$s->kelas}, Guru ID: {$s->guru_id} (" . ($teacher ? $teacher->nama : "NOT FOUND") . ")" . PHP_EOL;
}

echo PHP_EOL . "=== TEST getTeachersOnLeave LOGIC ===" . PHP_EOL;

// Simulate the logic from controller
$leaves = \App\Models\Leave::with(['teacher', 'substituteTeacher'])
    ->where('status', 'approved')
    ->where(function ($query) use ($startOfWeek, $endOfWeek) {
        $query->whereBetween('start_date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->orWhereBetween('end_date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->orWhere(function ($q) use ($startOfWeek, $endOfWeek) {
                $q->where('start_date', '<=', $startOfWeek->format('Y-m-d'))
                    ->where('end_date', '>=', $endOfWeek->format('Y-m-d'));
            });
    })
    ->get();

echo "Found " . count($leaves) . " approved leaves" . PHP_EOL;

foreach ($leaves as $leave) {
    echo PHP_EOL . "Leave ID: {$leave->id}" . PHP_EOL;
    echo "  Teacher ID: {$leave->teacher_id}" . PHP_EOL;
    echo "  Teacher name from relation: " . ($leave->teacher ? $leave->teacher->nama : "NULL") . PHP_EOL;

    // Get schedules for this teacher
    $schedules = \App\Models\Schedule::where('guru_id', $leave->teacher_id)->get();
    echo "  Schedules for this teacher: " . count($schedules) . PHP_EOL;

    foreach ($schedules as $schedule) {
        echo "    Schedule: {$schedule->hari} - {$schedule->kelas} - {$schedule->mata_pelajaran}" . PHP_EOL;

        // Check if schedule day matches leave period
        $currentDate = $startOfWeek->copy();
        while ($currentDate->lte($endOfWeek)) {
            $dayName = getDayName($currentDate->dayOfWeekIso);

            $inLeavePeriod = $currentDate->gte($leave->start_date) && $currentDate->lte($leave->end_date);

            if ($schedule->hari === $dayName && $inLeavePeriod) {
                echo "      MATCH! Date: " . $currentDate->format('Y-m-d') . " matches schedule day {$dayName} and is in leave period" . PHP_EOL;
            }
            $currentDate->addDay();
        }
    }
}

function getDayName($dayOfWeek): string
{
    $days = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    return $days[$dayOfWeek] ?? 'Unknown';
}
