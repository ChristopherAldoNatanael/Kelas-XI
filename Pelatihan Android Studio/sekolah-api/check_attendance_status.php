<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TeacherAttendance;

echo "=== Current Attendance Data ===\n\n";

$attendances = TeacherAttendance::select('id', 'status', 'tanggal', 'guru_id', 'schedule_id')
    ->orderBy('id', 'desc')
    ->limit(15)
    ->get();

echo "ID\tStatus\t\tTanggal\t\tGuru ID\n";
echo "--------------------------------------------\n";

foreach ($attendances as $att) {
    echo "{$att->id}\t{$att->status}\t\t{$att->tanggal->format('Y-m-d')}\t{$att->guru_id}\n";
}

echo "\n=== Status Count ===\n";
$statusCounts = TeacherAttendance::selectRaw('status, count(*) as total')
    ->groupBy('status')
    ->get();

foreach ($statusCounts as $sc) {
    echo "{$sc->status}: {$sc->total}\n";
}
