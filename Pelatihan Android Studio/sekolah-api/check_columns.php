<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = Illuminate\Support\Facades\Schema::getColumnListing('teacher_attendances');
foreach ($columns as $col) {
    $type = Illuminate\Support\Facades\Schema::getColumnType('teacher_attendances', $col);
    echo "$col: $type\n";
}
