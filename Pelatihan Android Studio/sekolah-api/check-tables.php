<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING TABLE STRUCTURES ===\n\n";

// Check users table
echo "USERS TABLE:\n";
$columns = DB::select("DESCRIBE users");
foreach ($columns as $col) {
    echo "  {$col->Field} - {$col->Type}\n";
}

echo "\nSUBJECTS TABLE:\n";
$columns = DB::select("DESCRIBE subjects");
foreach ($columns as $col) {
    echo "  {$col->Field} - {$col->Type}\n";
}

echo "\nCLASSES TABLE:\n";
$columns = DB::select("DESCRIBE classes");
foreach ($columns as $col) {
    echo "  {$col->Field} - {$col->Type}\n";
}

echo "\nTEACHERS TABLE:\n";
$columns = DB::select("DESCRIBE teachers");
foreach ($columns as $col) {
    echo "  {$col->Field} - {$col->Type}\n";
}
