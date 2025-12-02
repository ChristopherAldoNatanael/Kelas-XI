<?php
// Test kepala sekolah dashboard with week_offset=-1

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== Testing Dashboard with week_offset=-1 ===" . PHP_EOL;

try {
    $request = Illuminate\Http\Request::create('/api/kepala-sekolah/dashboard?week_offset=-1', 'GET');
    $response = $kernel->handle($request);

    $statusCode = $response->getStatusCode();
    echo "Status Code: $statusCode" . PHP_EOL;

    if ($statusCode !== 200) {
        echo "Response: " . $response->getContent() . PHP_EOL;
    } else {
        $data = json_decode($response->getContent(), true);
        echo "Success: " . ($data['success'] ? 'true' : 'false') . PHP_EOL;
        if (isset($data['data']['week_info'])) {
            echo "Week: " . $data['data']['week_info']['week_label'] . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace: " . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}
