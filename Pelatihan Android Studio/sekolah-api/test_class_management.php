<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$response = app()->handle(Illuminate\Http\Request::create('/api/kurikulum/classes', 'GET'));
$data = json_decode($response->getContent(), true);

echo "=== CLASS MANAGEMENT API TEST ===" . PHP_EOL;
echo "Success: " . ($data['success'] ? 'YES' : 'NO') . PHP_EOL;
echo "Date: " . ($data['date'] ?? 'N/A') . PHP_EOL;
echo "Day: " . ($data['day'] ?? 'N/A') . PHP_EOL;

if (isset($data['summary'])) {
    echo PHP_EOL . "=== SUMMARY ===" . PHP_EOL;
    echo "Tidak Hadir: " . $data['summary']['tidak_hadir_count'] . PHP_EOL;
    echo "Telat: " . $data['summary']['telat_count'] . PHP_EOL;
    echo "Izin: " . $data['summary']['izin_count'] . PHP_EOL;
    echo "Pending: " . $data['summary']['pending_count'] . PHP_EOL;
}

if (isset($data['grouped_by_class'])) {
    echo PHP_EOL . "=== GROUPED BY CLASS ===" . PHP_EOL;
    foreach ($data['grouped_by_class'] as $group) {
        echo "- " . $group['class_name'] . " (" . $group['total_issues'] . " issues)" . PHP_EOL;
        foreach ($group['schedules'] as $schedule) {
            echo "    • " . $schedule['subject_name'] . " - " . $schedule['teacher_name'] . " [" . $schedule['status'] . "]" . PHP_EOL;
        }
    }
}
