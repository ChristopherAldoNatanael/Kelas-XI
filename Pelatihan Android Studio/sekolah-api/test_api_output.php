<?php

/**
 * Test API class-management output
 */
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\KurikulumController;

// Create controller and call method
$controller = new KurikulumController();
$request = new Request();

try {
    $response = $controller->classManagement($request);
    $content = $response->getContent();

    echo "Response Status: " . $response->getStatusCode() . "\n\n";

    // Try to decode JSON
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Raw content (first 2000 chars):\n";
        echo substr($content, 0, 2000) . "\n";
    } else {
        echo "JSON decoded successfully!\n";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . $data['message'] . "\n";
        echo "Summary:\n";
        print_r($data['summary']);

        // Check grouped_by_class structure
        if (isset($data['grouped_by_class'])) {
            echo "\nGrouped by class count: " . count($data['grouped_by_class']) . "\n";

            // Check first class
            if (count($data['grouped_by_class']) > 0) {
                $firstClass = $data['grouped_by_class'][0];
                echo "\nFirst class structure:\n";
                echo "  - class_name: " . $firstClass['class_name'] . "\n";
                echo "  - total_issues: " . $firstClass['total_issues'] . "\n";
                echo "  - schedules count: " . count($firstClass['schedules']) . "\n";

                // Check first schedule
                if (count($firstClass['schedules']) > 0) {
                    $firstSchedule = $firstClass['schedules'][0];
                    echo "\nFirst schedule in first class:\n";
                    foreach ($firstSchedule as $key => $value) {
                        if (is_null($value)) {
                            echo "  - $key: NULL\n";
                        } elseif (is_bool($value)) {
                            echo "  - $key: " . ($value ? 'true' : 'false') . "\n";
                        } elseif (is_array($value)) {
                            echo "  - $key: [array]\n";
                        } else {
                            echo "  - $key: $value\n";
                        }
                    }
                }
            }
        }
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
