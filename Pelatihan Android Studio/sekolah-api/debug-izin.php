<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== DEBUGGING IZIN DATA ===" . PHP_EOL;

// Current week dates
$today = Carbon::now();
$startOfWeek = $today->copy()->startOfWeek();
$endOfWeek = $startOfWeek->copy()->endOfWeek();

echo "Today: " . $today->format('Y-m-d') . " (" . $today->isoFormat('dddd') . ")" . PHP_EOL;
echo "Week Start: " . $startOfWeek->format('Y-m-d') . PHP_EOL;
echo "Week End: " . $endOfWeek->format('Y-m-d') . PHP_EOL;

echo PHP_EOL . "=== LEAVES TABLE DETAILS ===" . PHP_EOL;
$leaves = DB::table('leaves')->get();
foreach ($leaves as $leave) {
    echo "ID: {$leave->id}" . PHP_EOL;
    echo "  Teacher ID: {$leave->teacher_id}" . PHP_EOL;
    echo "  Status: {$leave->status}" . PHP_EOL;
    echo "  Reason: {$leave->reason}" . PHP_EOL;
    echo "  Start: {$leave->start_date}, End: {$leave->end_date}" . PHP_EOL;
    echo "  Substitute: {$leave->substitute_teacher_id}" . PHP_EOL;

    // Check if teacher exists in teachers table
    $teacher = DB::table('teachers')->find($leave->teacher_id);
    echo "  Teacher in teachers table: " . ($teacher ? $teacher->nama : "NOT FOUND!") . PHP_EOL;

    // Check if teacher exists in users table
    $user = DB::table('users')->find($leave->teacher_id);
    echo "  User in users table: " . ($user ? $user->name : "NOT FOUND!") . PHP_EOL;
    echo PHP_EOL;
}

echo "=== TEACHERS TABLE ===" . PHP_EOL;
$teachers = DB::table('teachers')->select('id', 'nama', 'nip')->get();
foreach ($teachers as $t) {
    echo "ID: {$t->id}, Nama: {$t->nama}, NIP: {$t->nip}" . PHP_EOL;
}

echo PHP_EOL . "=== APPROVED LEAVES IN CURRENT WEEK ===" . PHP_EOL;
$approvedLeaves = DB::table('leaves')
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

echo "Found " . count($approvedLeaves) . " approved leaves in current week" . PHP_EOL;
foreach ($approvedLeaves as $leave) {
    echo "ID: {$leave->id}, Teacher: {$leave->teacher_id}, Period: {$leave->start_date} to {$leave->end_date}" . PHP_EOL;
}

// Check Leave model relationship
echo PHP_EOL . "=== LEAVE MODEL TEST ===" . PHP_EOL;
try {
    $leaveModel = \App\Models\Leave::with(['teacher'])->first();
    if ($leaveModel) {
        echo "Leave ID: {$leaveModel->id}" . PHP_EOL;
        echo "Teacher relationship: " . ($leaveModel->teacher ? $leaveModel->teacher->nama : "NULL - relationship broken!") . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
