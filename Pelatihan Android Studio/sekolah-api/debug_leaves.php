<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CHECKING leaves TABLE ===" . PHP_EOL;

if (!Schema::hasTable('leaves')) {
    echo "Table 'leaves' does not exist!" . PHP_EOL;
    exit(0);
}

$columns = Schema::getColumnListing('leaves');
echo "Columns: " . implode(', ', $columns) . PHP_EOL . PHP_EOL;

$records = DB::table('leaves')
    ->select('*')
    ->get();

echo "Total records: " . $records->count() . PHP_EOL;

foreach ($records as $r) {
    echo PHP_EOL . "Record:" . PHP_EOL;
    foreach ((array)$r as $key => $value) {
        echo "  {$key}: {$value}" . PHP_EOL;
    }
}
