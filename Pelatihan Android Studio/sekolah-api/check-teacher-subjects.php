<?php

require_once 'vendor/autoload.php';
$laravel = require_once 'bootstrap/app.php';
$laravel->boot();

use App\Models\Teacher;
use App\Models\Subject;

echo "🎯 Teachers and their subjects:\n";
echo "================================\n";
$teachers = Teacher::all();
foreach($teachers as $teacher) {
    echo "- {$teacher->name} teaches: " . ($teacher->mata_pelajaran ?? 'N/A') . "\n";
}

echo "\n📚 Available subjects:\n";
echo "=====================\n";
$subjects = Subject::all();
foreach($subjects as $subject) {
    echo "- {$subject->nama} (code: {$subject->kode})\n";
}

echo "\n🔍 Matching check:\n";
echo "==================\n";
foreach($subjects as $subject) {
    $matchingTeachers = $teachers->where('mata_pelajaran', $subject->nama);
    echo "Subject '{$subject->nama}' has " . $matchingTeachers->count() . " teacher(s):\n";
    foreach($matchingTeachers as $teacher) {
        echo "  - {$teacher->name}\n";
    }
}
