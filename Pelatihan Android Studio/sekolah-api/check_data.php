<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Attendance Data ===\n\n";

$data = DB::select("SELECT id, status, keterangan FROM teacher_attendances WHERE tanggal = ? LIMIT 15", [date('Y-m-d')]);

echo "Total: " . count($data) . " records\n\n";

foreach ($data as $d) {
    echo "ID: {$d->id} | Status: '{$d->status}' | " . substr($d->keterangan ?? 'NULL', 0, 50) . "\n";
}

// Count by status
echo "\n=== Status Summary ===\n";
$summary = DB::select("SELECT status, COUNT(*) as cnt FROM teacher_attendances WHERE tanggal = ? GROUP BY status", [date('Y-m-d')]);
foreach ($summary as $s) {
    echo "  '{$s->status}': {$s->cnt}\n";
}
