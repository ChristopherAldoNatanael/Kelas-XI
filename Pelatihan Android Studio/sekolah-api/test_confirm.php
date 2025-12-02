<?php

/**
 * Test confirm attendance API
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\TeacherAttendance;
use App\Models\User;

echo "=== Testing Confirm Attendance ===\n\n";

// Get one pending attendance
$pendingAttendance = TeacherAttendance::where('status', 'pending')->first();

if (!$pendingAttendance) {
    echo "No pending attendance found!\n";
    exit;
}

echo "Found pending attendance:\n";
echo "  ID: {$pendingAttendance->id}\n";
echo "  Teacher: {$pendingAttendance->guru->name}\n";
echo "  Status: {$pendingAttendance->status}\n";
echo "  Keterangan: {$pendingAttendance->keterangan}\n\n";

// Get a kurikulum user
$kurikulumUser = User::where('role', 'kurikulum')->first();
if (!$kurikulumUser) {
    echo "No kurikulum user found!\n";
    exit;
}

echo "Simulating confirm by: {$kurikulumUser->name}\n\n";

// Simulate the confirm logic
$newStatus = 'hadir';
$pendingAttendance->status = $newStatus;
$pendingAttendance->keterangan = ($pendingAttendance->keterangan ?? '') . " | Dikonfirmasi sebagai '{$newStatus}' oleh Kurikulum";
$pendingAttendance->assigned_by = $kurikulumUser->id;
$pendingAttendance->save();

echo "After confirmation:\n";
echo "  Status: {$pendingAttendance->status}\n";
echo "  Keterangan: {$pendingAttendance->keterangan}\n\n";

// Count remaining pending
$remainingPending = TeacherAttendance::where('status', 'pending')->count();
echo "Remaining pending: $remainingPending\n";

// Current distribution
echo "\nCurrent status distribution:\n";
$summary = DB::table('teacher_attendances')
    ->select('status', DB::raw('COUNT(*) as count'))
    ->groupBy('status')
    ->get();

foreach ($summary as $row) {
    echo "  - {$row->status}: {$row->count}\n";
}
