<?php

/**
 * Deep check for null values in API response
 */
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\KurikulumController;

$controller = new KurikulumController();
$request = new Request();

$response = $controller->classManagement($request);
$data = json_decode($response->getContent(), true);

echo "=== Checking all teacher_nip values ===\n\n";

$classIndex = 0;
foreach ($data['grouped_by_class'] as $class) {
    echo "Class [$classIndex]: " . $class['class_name'] . "\n";

    $scheduleIndex = 0;
    foreach ($class['schedules'] as $schedule) {
        $nip = $schedule['teacher_nip'];
        $nipType = gettype($nip);

        if ($nip === null) {
            echo "  ⚠ Schedule [$scheduleIndex]: teacher_nip is NULL\n";
        } elseif ($nip === '') {
            echo "  ⚠ Schedule [$scheduleIndex]: teacher_nip is EMPTY STRING\n";
        } elseif (!is_string($nip)) {
            echo "  ⚠ Schedule [$scheduleIndex]: teacher_nip is $nipType: " . var_export($nip, true) . "\n";
        }

        // Check if NIP might be a number that's too large
        if (is_string($nip) && strlen($nip) > 15) {
            echo "  ℹ Schedule [$scheduleIndex]: teacher_nip is long string ($nip)\n";
        }

        $scheduleIndex++;
    }

    $classIndex++;
}

echo "\n=== Full JSON check ===\n";
$jsonOutput = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
echo "JSON is valid, total length: " . strlen($jsonOutput) . " bytes\n";

// Check the specific problematic path
if (isset($data['grouped_by_class'][5]['schedules'][1])) {
    echo "\n=== Checking $.grouped_by_class[5].schedules[1] ===\n";
    $problematic = $data['grouped_by_class'][5]['schedules'][1];
    echo "teacher_id: " . var_export($problematic['teacher_id'], true) . "\n";
    echo "teacher_name: " . var_export($problematic['teacher_name'], true) . "\n";
    echo "teacher_nip: " . var_export($problematic['teacher_nip'], true) . " (type: " . gettype($problematic['teacher_nip']) . ")\n";
}

echo "\n=== Done ===\n";
