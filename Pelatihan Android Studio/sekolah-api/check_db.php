<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ClassModel;
use App\Models\Schedule;

echo "Classes with 'Rekayasa Perangkat Lunak': " . ClassModel::where('major', 'Rekayasa Perangkat Lunak')->count() . "\n";
echo "Classes with 'RPL': " . ClassModel::where('major', 'RPL')->count() . "\n";
echo "All classes: " . ClassModel::count() . "\n";
echo "Schedules: " . Schedule::count() . "\n";

$classes = ClassModel::select('id', 'name', 'level', 'major')->get();
echo "\nClasses:\n";
foreach ($classes as $class) {
    echo "- {$class->name} (Level: {$class->level}, Major: {$class->major})\n";
}
