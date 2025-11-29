<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Teacher;

echo "🧪 Testing Teacher Creation Flow\n";
echo "================================\n\n";

// Check available users
echo "Available users for teacher assignment:\n";
$availableUsers = User::whereDoesntHave('teacher')->get();
foreach ($availableUsers as $user) {
    echo "- {$user->name} ({$user->email}) - ID: {$user->id}\n";
}
echo "\n";

// Check current teachers
echo "Current teachers:\n";
$teachers = Teacher::with('user')->get();
foreach ($teachers as $teacher) {
    $userName = $teacher->user ? $teacher->user->name : 'No user';
    echo "- {$userName} - NIP: {$teacher->nip} - Status: {$teacher->status}\n";
}
echo "\n";

// Test teacher creation data
if ($availableUsers->count() > 0) {
    $testUser = $availableUsers->first();

    echo "Testing teacher creation with user: {$testUser->name}\n";

    $teacherData = [
        'user_id' => $testUser->id,
        'nip' => '1234567890',
        'teacher_code' => 'TCH001',
        'position' => 'Senior Teacher',
        'department' => 'Mathematics',
        'expertise' => 'Calculus',
        'certification' => 'S.Pd',
        'join_date' => '2024-01-15',
        'status' => 'active'
    ];

    try {
        $teacher = Teacher::create($teacherData);
        echo "✅ Teacher created successfully!\n";
        echo "Teacher ID: {$teacher->id}\n";
        echo "NIP: {$teacher->nip}\n";
        echo "Name: {$teacher->name}\n";
        echo "Email: {$teacher->email}\n";
    } catch (Exception $e) {
        echo "❌ Failed to create teacher: {$e->getMessage()}\n";
    }
} else {
    echo "❌ No available users for teacher assignment\n";
}

echo "\nTest completed!\n";
