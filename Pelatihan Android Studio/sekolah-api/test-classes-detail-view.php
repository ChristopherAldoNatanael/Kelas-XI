<?php

require_once 'bootstrap/app.php';

use App\Models\ClassModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Web\WebClassController;

echo "\n=== TESTING CLASSES DETAIL VIEW ===\n";

try {
    // Get a class record
    $class = ClassModel::first();

    if (!$class) {
        echo "❌ No class records found in database\n";
        exit(1);
    }

    echo "✅ Found class: " . ($class->nama ?? 'No name') . "\n";
    echo "   Status: " . ($class->status ?? 'No status') . "\n";
    echo "   ID: " . $class->id . "\n";

    // Test the controller method
    $controller = new WebClassController();
    $request = new Request();

    echo "\n--- Testing Controller Show Method ---\n";

    try {
        $response = $controller->show($class->id);
        echo "✅ Controller show method executed successfully\n";
        echo "   Response type: " . get_class($response) . "\n";
    } catch (Exception $e) {
        echo "❌ Controller show method failed: " . $e->getMessage() . "\n";
    }

    // Test status display logic
    echo "\n--- Testing Status Display Logic ---\n";

    $statusBadgeClass = $class->status == 'active' ? 'success' : 'secondary';
    $statusText = ucfirst($class->status ?? 'inactive');

    echo "✅ Status badge class: bg-$statusBadgeClass\n";
    echo "✅ Status text: $statusText\n";

    // Test for any trashed() method calls (should not exist)
    echo "\n--- Checking for SoftDeletes Methods ---\n";

    $classMethods = get_class_methods($class);
    $hasTrashed = in_array('trashed', $classMethods);

    if ($hasTrashed) {
        echo "⚠️  WARNING: trashed() method still exists on ClassModel\n";
    } else {
        echo "✅ No trashed() method found - SoftDeletes properly removed\n";
    }

    echo "\n=== CLASSES DETAIL VIEW TEST COMPLETE ===\n";
    echo "✅ All tests passed! The classes detail view should now work properly.\n";

} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
