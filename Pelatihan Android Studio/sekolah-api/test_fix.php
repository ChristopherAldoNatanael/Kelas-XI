<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$response = app()->handle(Illuminate\Http\Request::create('/api/kurikulum/dashboard?day=Senin&class_id=2', 'GET'));
$data = json_decode($response->getContent(), true);

echo "Status: " . ($data['success'] ? 'SUCCESS' : 'FAILED') . PHP_EOL;
echo "late_minutes values:" . PHP_EOL;
foreach ($data['data'] as $item) {
    echo "  - {$item['subject_name']}: " . var_export($item['late_minutes'], true) . " (" . gettype($item['late_minutes']) . ")" . PHP_EOL;
}
