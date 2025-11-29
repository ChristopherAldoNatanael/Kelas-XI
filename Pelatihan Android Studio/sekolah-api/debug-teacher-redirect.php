<?php

echo "=== DEBUGGING TEACHER EDIT REDIRECT ISSUE ===\n";

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // 1. Check if user authentication persists after teacher update
    echo "1. Testing teacher update process...\n";
    
    $teacher = DB::table('teachers')->first();
    if (!$teacher) {
        echo "   ✗ No teacher found\n";
        exit;
    }
    
    echo "   Teacher found: ID={$teacher->id}, Name={$teacher->name}\n";
    
    // 2. Test update directly
    $originalName = $teacher->name;
    $testName = "Test Teacher " . time();
    
    echo "2. Performing direct update...\n";
    $updated = DB::table('teachers')
        ->where('id', $teacher->id)
        ->update([
            'name' => $testName,
            'updated_at' => now()
        ]);
    
    if ($updated) {
        echo "   ✓ Direct update successful\n";
        
        // Check if update actually worked
        $updatedTeacher = DB::table('teachers')->where('id', $teacher->id)->first();
        echo "   Updated name: {$updatedTeacher->name}\n";
        
        // Restore original name
        DB::table('teachers')
            ->where('id', $teacher->id)
            ->update([
                'name' => $originalName,
                'updated_at' => now()
            ]);
        echo "   ✓ Name restored\n";
    } else {
        echo "   ✗ Direct update failed\n";
    }
    
    // 3. Check WebTeacherController update method
    echo "3. Testing WebTeacherController update method...\n";
    
    // Simulate request data
    $requestData = [
        'name' => 'Test Teacher Update',
        'email' => $teacher->email,
        'mata_pelajaran' => $teacher->mata_pelajaran,
        'is_banned' => $teacher->is_banned
    ];
    
    // Create mock request
    $request = new \Illuminate\Http\Request();
    $request->merge($requestData);
    
    // Test validation
    $validator = \Illuminate\Support\Facades\Validator::make($requestData, [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:teachers,email,' . $teacher->id,
        'mata_pelajaran' => 'required|string|max:255',
        'is_banned' => 'boolean',
    ]);
    
    if ($validator->passes()) {
        echo "   ✓ Validation passed\n";
    } else {
        echo "   ✗ Validation failed: " . implode(', ', $validator->errors()->all()) . "\n";
    }
    
    // 4. Check for any redirect issues in the controller
    echo "4. Checking controller redirect logic...\n";
    
    // Check if routes exist
    $routeExists = \Illuminate\Support\Facades\Route::has('web-teachers.index');
    echo "   Route 'web-teachers.index' exists: " . ($routeExists ? 'Yes' : 'No') . "\n";
    
    $updateRouteExists = \Illuminate\Support\Facades\Route::has('web-teachers.update');
    echo "   Route 'web-teachers.update' exists: " . ($updateRouteExists ? 'Yes' : 'No') . "\n";
    
    // 5. Check session configuration
    echo "5. Checking session configuration...\n";
    echo "   Session driver: " . config('session.driver') . "\n";
    echo "   Session lifetime: " . config('session.lifetime') . " minutes\n";
    echo "   Session path: " . config('session.path') . "\n";
    
    // 6. Check if there are any error logs
    echo "6. Recent Laravel logs (if available)...\n";
    $logPath = storage_path('logs/laravel.log');
    if (file_exists($logPath)) {
        $logs = file_get_contents($logPath);
        $recentLogs = array_slice(explode("\n", $logs), -10);
        foreach ($recentLogs as $log) {
            if (trim($log)) {
                echo "   " . trim($log) . "\n";
            }
        }
    } else {
        echo "   No log file found at: $logPath\n";
    }
    
    echo "\n=== DEBUGGING COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
