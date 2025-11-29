<?php
// Test script untuk debug schedule import

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test lookup guru
$codes = ['TCH002', 'TCH003', 'TCH004', 'TCH006', 'TCH011', 'TCH012', 'TCH013', 'TCH014', 'TCH015'];

echo "Testing Teacher Lookup:\n";
echo "========================\n";

foreach ($codes as $code) {
    $teacher = App\Models\Teacher::where('teacher_code', $code)->first();
    if ($teacher) {
        echo "✓ $code -> ID: {$teacher->id}, Nama: {$teacher->nama}\n";
    } else {
        echo "✗ $code -> NOT FOUND\n";
    }
}

echo "\n\nAll Teachers in DB:\n";
echo "===================\n";
$all = App\Models\Teacher::select('id', 'nama', 'teacher_code')->get();
foreach ($all as $t) {
    echo "ID: {$t->id}, Code: '{$t->teacher_code}', Nama: {$t->nama}\n";
}
