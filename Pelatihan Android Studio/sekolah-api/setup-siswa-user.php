<?php

/**
 * Setup Siswa User untuk Testing
 * Creates a student user with class_id assigned
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Hash;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SETUP SISWA USER ===\n\n";

// Check if we have any classes
$classes = \App\Models\ClassModel::first();
if (!$classes) {
    echo "❌ No classes found! Creating a dummy class...\n";
    $class = \App\Models\ClassModel::create([
        'name' => 'XI RPL',
        'level' => 11,
        'major' => 'Rekayasa Perangkat Lunak',
        'academic_year' => '2024/2025',
        'homeroom_teacher_id' => 1,
        'capacity' => 36,
        'status' => 'active'
    ]);
    echo "✅ Class created: XI RPL (ID: {$class->id})\n";
} else {
    $class = $classes;
    echo "✅ Using existing class: {$class->name} (ID: {$class->id})\n";
}

// Check if siswa user exists
$siswa = \App\Models\User::where('email', 'siswa@example.com')->first();

if ($siswa) {
    echo "User siswa@example.com already exists. Updating...\n";
    $siswa->update([
        'password' => Hash::make('password123'),
        'role' => 'siswa',
        'class_id' => $class->id,
        'status' => 'active'
    ]);
} else {
    echo "Creating new siswa user...\n";
    $siswa = \App\Models\User::create([
        'nama' => 'Siswa Test',
        'email' => 'siswa@example.com',
        'password' => Hash::make('password123'),
        'role' => 'siswa',
        'class_id' => $class->id,
        'status' => 'active',
        'phone' => '081234567890',
        'address' => 'Jl. Test No. 123'
    ]);
}

echo "\n✅ Siswa user ready:\n";
echo "   Email: siswa@example.com\n";
echo "   Password: password123\n";
echo "   Role: {$siswa->role}\n";
echo "   Class ID: {$siswa->class_id}\n";
echo "   Class Name: {$class->name}\n";

// Create some dummy schedules if not exist
$scheduleCount = \App\Models\Schedule::where('class_id', $class->id)->count();
if ($scheduleCount === 0) {
    echo "\n📅 Creating dummy schedules for testing...\n";

    // Get or create a subject
    $subject = \App\Models\Subject::first();
    if (!$subject) {
        $subject = \App\Models\Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK',
            'category' => 'Wajib',
            'credit_hours' => 4,
            'semester' => 1,
            'status' => 'active'
        ]);
    }

    // Get or create a teacher
    $teacher = \App\Models\Teacher::first();
    if (!$teacher) {
        $teacherUser = \App\Models\User::create([
            'nama' => 'Guru Test',
            'email' => 'guru@example.com',
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'status' => 'active'
        ]);

        $teacher = \App\Models\Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '1234567890',
            'teacher_code' => 'TCH001',
            'position' => 'Guru Tetap',
            'department' => 'Matematika',
            'join_date' => now(),
            'status' => 'active'
        ]);
    }

    // Create today's schedule
    $today = now()->dayOfWeek; // 0=Sunday, 1=Monday, etc

    for ($i = 1; $i <= 5; $i++) {
        \App\Models\Schedule::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => $today,
            'period_number' => $i,
            'start_time' => sprintf('%02d:00:00', 7 + $i),
            'end_time' => sprintf('%02d:45:00', 7 + $i),
            'academic_year' => '2024/2025',
            'semester' => '1',
            'status' => 'active'
        ]);
    }

    echo "✅ Created 5 schedules for today (day $today)\n";
}

echo "\n=== SETUP COMPLETE! ===\n";
echo "You can now test with: php test-kehadiran-debug.php\n";
