<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Check teacher_attendances table columns
echo "teacher_attendances table columns:\n";
$cols = Schema::getColumnListing('teacher_attendances');
print_r($cols);

// Check teachers table columns
echo "\n\nteachers table columns:\n";
$cols = Schema::getColumnListing('teachers');
print_r($cols);

// Check if guru_id refers to teachers or users
echo "\n\nChecking TeacherAttendance guru_id values:\n";
$attendance = DB::table('teacher_attendances')->first();
if ($attendance) {
    echo "Sample attendance record:\n";
    print_r($attendance);

    echo "\n\nChecking if guru_id exists in teachers table:\n";
    $teacher = DB::table('teachers')->where('id', $attendance->guru_id)->first();
    if ($teacher) {
        echo "Found in teachers table: " . $teacher->nama . "\n";
    } else {
        echo "NOT found in teachers table\n";
    }

    echo "\n\nChecking if guru_id exists in users table:\n";
    $user = DB::table('users')->where('id', $attendance->guru_id)->first();
    if ($user) {
        echo "Found in users table: " . $user->name . "\n";
    } else {
        echo "NOT found in users table\n";
    }
}
