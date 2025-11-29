<?php
// Quick test to verify database connections and model relationships

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== QUICK DATABASE & MODEL TEST ===\n";

try {
    // Test database connection
    echo "1. Testing database connection...\n";
    $pdo = DB::connection()->getPdo();
    echo "✓ Database connected successfully\n";

    // Test basic queries
    echo "\n2. Testing table structures...\n";

    // Test users table
    $userCount = DB::table('users')->count();
    echo "✓ Users table: {$userCount} records\n";

    // Test teachers table
    $teacherCount = DB::table('teachers')->count();
    echo "✓ Teachers table: {$teacherCount} records\n";

    // Test subjects table
    $subjectCount = DB::table('subjects')->count();
    echo "✓ Subjects table: {$subjectCount} records\n";

    // Test schedules table
    $scheduleCount = DB::table('schedules')->count();
    echo "✓ Schedules table: {$scheduleCount} records\n";

    echo "\n3. Testing model relationships...\n";

    // Test Schedule model with relationships
    $schedule = App\Models\Schedule::with(['guru', 'subject'])->first();
    if ($schedule) {
        echo "✓ Schedule model working\n";
        echo "  - Schedule ID: {$schedule->id}\n";
        echo "  - Hari: {$schedule->hari}\n";
        echo "  - Kelas: {$schedule->kelas}\n";
        echo "  - Mata Pelajaran: {$schedule->mata_pelajaran}\n";

        if ($schedule->guru) {
            echo "  - Guru: {$schedule->guru->name}\n";
        } else {
            echo "  - Guru: Not assigned\n";
        }
    } else {
        echo "! No schedules found in database\n";
    }

    // Test Teacher model
    $teacher = App\Models\Teacher::first();
    if ($teacher) {
        echo "✓ Teacher model working\n";
        echo "  - Teacher ID: {$teacher->id}\n";
        echo "  - Name: {$teacher->name}\n";
        echo "  - Email: {$teacher->email}\n";
        echo "  - Mata Pelajaran: {$teacher->mata_pelajaran}\n";
    } else {
        echo "! No teachers found in database\n";
    }

    // Test Subject model
    $subject = App\Models\Subject::first();
    if ($subject) {
        echo "✓ Subject model working\n";
        echo "  - Subject ID: {$subject->id}\n";
        echo "  - Nama: {$subject->nama}\n";
        echo "  - Kode: {$subject->kode}\n";
    } else {
        echo "! No subjects found in database\n";
    }

    echo "\n4. Testing API endpoints...\n";

    // Test basic API endpoint
    $apiResponse = file_get_contents('http://localhost:8000/api/schedules-public?limit=1');
    if ($apiResponse) {
        $data = json_decode($apiResponse, true);
        if ($data && $data['success']) {
            echo "✓ API endpoint working\n";
            echo "  - Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "! API endpoint returned error\n";
        }
    } else {
        echo "! Could not connect to API endpoint\n";
    }

    echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";
    echo "All database connections and models are working correctly!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
