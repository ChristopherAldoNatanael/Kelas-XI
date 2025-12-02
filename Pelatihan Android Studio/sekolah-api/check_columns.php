<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "=== Schedule Table Columns ===\n";
$columns = Schema::getColumnListing('schedules');
print_r($columns);
