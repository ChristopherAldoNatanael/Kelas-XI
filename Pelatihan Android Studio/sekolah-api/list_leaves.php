<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$leaves = \App\Models\Leave::where('status', 'approved')->get();
echo "=== All Approved Leaves ===\n\n";
foreach ($leaves as $l) {
    echo "ID: {$l->id}, Teacher: {$l->teacher_id}, Start: {$l->start_date}, End: {$l->end_date}, Reason: {$l->reason}\n";
}
