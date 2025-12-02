<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schedules = \App\Models\Schedule::where('guru_id', 7)->where('hari', 'Senin')->get();
echo "=== Teacher 7 (Siti Nurhaliza) Schedules on Senin ===\n\n";
echo "Count: " . $schedules->count() . "\n\n";
foreach ($schedules as $s) {
    echo "{$s->id}: {$s->kelas} - {$s->mata_pelajaran}\n";
}
