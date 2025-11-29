<?php

require 'vendor/autoload.php';

use App\Http\Controllers\Web\WebUserController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

// Create a mock request
$request = new Request();
$request->merge([
    'skip_duplicates' => '1',
    'update_existing' => '0'
]);

// Create a mock uploaded file
$uploadedFile = new UploadedFile(
    __DIR__ . '/test_users.csv',
    'test_users.csv',
    'text/csv',
    null,
    true
);

// Set the file on the request
$request->files->set('file', $uploadedFile);

// Create controller instance
$controller = new WebUserController();

try {
    $result = $controller->importStore($request);
    echo "Import result: " . json_encode($result->getData(), JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
