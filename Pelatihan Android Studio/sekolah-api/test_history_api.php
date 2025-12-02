<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\KurikulumController;
use Illuminate\Http\Request;

$controller = new KurikulumController();
$request = new Request();

try {
    $response = $controller->attendanceHistory($request);
    $data = json_decode($response->getContent(), true);

    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Message: " . $data['message'] . "\n";
    echo "Total records: " . count($data['data']) . "\n\n";

    if (count($data['data']) > 0) {
        echo "First record:\n";
        print_r($data['data'][0]);
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
