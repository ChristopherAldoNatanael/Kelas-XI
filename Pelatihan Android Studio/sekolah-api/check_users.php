<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Check users table columns
echo "Users table columns:\n";
$cols = Schema::getColumnListing('users');
print_r($cols);

// Check Guru model
echo "\n\nCheck Guru model:\n";
$guru = DB::table('users')->where('role', 'guru')->first();
if ($guru) {
    echo "Guru record:\n";
    print_r($guru);
}
