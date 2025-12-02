<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

use Illuminate\Http\Request;

try {
    echo "=== TESTING API ENDPOINT ===" . PHP_EOL;

    // Create a request similar to what the Android app sends
    $request = Request::create('/api/kurikulum/dashboard', 'GET', [
        'day' => 'Senin',
        'class_id' => '2',
        'week_offset' => '0'
    ]);

    // Process the request
    $response = $kernel->handle($request);

    echo "Status Code: " . $response->getStatusCode() . PHP_EOL;
    echo "Content Type: " . $response->headers->get('Content-Type') . PHP_EOL;
    echo PHP_EOL . "Response Body:" . PHP_EOL;

    $content = $response->getContent();
    if (strlen($content) > 2000) {
        echo substr($content, 0, 2000) . "..." . PHP_EOL;
    } else {
        echo $content . PHP_EOL;
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . PHP_EOL;
    echo "Line: " . $e->getLine() . PHP_EOL;
    echo "Trace: " . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . PHP_EOL;
    echo "Line: " . $e->getLine() . PHP_EOL;
    echo "Trace: " . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}
