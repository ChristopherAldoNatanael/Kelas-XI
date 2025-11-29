<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ClassModel;

echo "Classes in database:\n";
echo "===================\n\n";

$classes = ClassModel::all();

if ($classes->isEmpty()) {
    echo "No classes found in database!\n\n";
    echo "Checking RPL classes specifically:\n";
    $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->whereIn('nama_kelas', ['X RPL', 'XI RPL', 'XII RPL'])
        ->get();

    echo "RPL classes found: " . $rplClasses->count() . "\n";
} else {
    foreach ($classes as $class) {
        echo "ID: {$class->id}\n";
        echo "Nama Kelas: {$class->nama_kelas}\n";
        echo "Major: {$class->major}\n";
        echo "Level: {$class->level}\n";
        echo "Status: {$class->status}\n";
        echo "---\n";
    }

    echo "\nChecking RPL classes specifically:\n";
    $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->whereIn('nama_kelas', ['X RPL', 'XI RPL', 'XII RPL'])
        ->get();

    echo "RPL classes found: " . $rplClasses->count() . "\n";
    foreach ($rplClasses as $class) {
        echo "- {$class->nama_kelas} (ID: {$class->id})\n";
    }
}
