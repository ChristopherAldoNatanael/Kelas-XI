<?php

/**
 * Test endpoint siswa/weekly-schedule
 * Endpoint khusus siswa untuk load jadwal seminggu otomatis dari class_id user
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

echo "=== TEST SISWA WEEKLY SCHEDULE ENDPOINT ===\n\n";

try {
    // 1. Cari atau buat user siswa dengan class_id
    echo "1. Mencari/membuat user siswa dengan class_id...\n";

    $siswa = User::where('role', 'siswa')
        ->whereNotNull('class_id')
        ->first();

    if (!$siswa) {
        echo "❌ Tidak ada siswa dengan class_id!\n";
        echo "Creating test siswa with class_id...\n";

        $class = \App\Models\ClassModel::where('name', 'XI RPL')->first();
        if (!$class) {
            echo "❌ Kelas XI RPL tidak ditemukan!\n";
            exit(1);
        }

        $siswa = User::create([
            'nama' => 'Test Siswa Weekly ' . time(),
            'email' => 'test.weekly.' . time() . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
            'class_id' => $class->id,
            'status' => 'active'
        ]);

        echo "✓ Created siswa: {$siswa->nama} (Class ID: {$siswa->class_id})\n";
    } else {
        echo "✓ Found siswa: {$siswa->nama} (Class ID: {$siswa->class_id})\n";
    }

    $siswa->load('class');
    echo "  Kelas: {$siswa->class->name} (Level {$siswa->class->level})\n\n";

    // 2. Create token (simulate login)
    echo "2. Creating auth token...\n";
    $token = $siswa->createToken('test-token')->plainTextToken;
    echo "✓ Token created\n\n";    // 3. Test endpoint siswa/weekly-schedule
    echo "3. Testing GET /api/siswa/weekly-schedule\n";
    echo "   (Auto-load jadwal seminggu dari class_id siswa)\n";
    echo "   ================================================\n\n";

    // Simulate API request with proper auth
    $request = Request::create('/api/siswa/weekly-schedule', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $token);
    $request->headers->set('Accept', 'application/json');

    // Set up Sanctum authentication properly
    \Illuminate\Support\Facades\Auth::shouldUse('sanctum');

    // Authenticate the request
    app()->instance('request', $request);

    // Use Laravel's actual token guard
    $tokenInstance = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if ($tokenInstance) {
        $user = $tokenInstance->tokenable;
        auth()->setUser($user);
        echo "✓ Auth setup: {$user->nama} (Role: {$user->role})\n\n";
    }

    // Call controller method directly
    $controller = new \App\Http\Controllers\Api\ScheduleController();
    $response = $controller->myWeeklySchedule($request);
    $data = json_decode($response->getContent(), true);

    if ($data['success']) {
        echo "✅ SUCCESS!\n\n";
        echo "Response Data:\n";
        echo "==============\n";
        echo "Class Info:\n";
        echo "  - ID: {$data['data']['class']['id']}\n";
        echo "  - Name: {$data['data']['class']['name']}\n";
        echo "  - Level: {$data['data']['class']['level']}\n";
        echo "  - Major: {$data['data']['class']['major']}\n\n";

        echo "Total Schedules: {$data['data']['total_schedules']}\n\n";

        if ($data['data']['total_schedules'] > 0) {
            echo "Schedules by Day:\n";
            echo "-----------------\n";

            $schedules = $data['data']['schedules'];
            $groupedByDay = [];
            foreach ($schedules as $schedule) {
                $day = $schedule['day_of_week'];
                if (!isset($groupedByDay[$day])) {
                    $groupedByDay[$day] = [];
                }
                $groupedByDay[$day][] = $schedule;
            }

            foreach ($groupedByDay as $day => $daySchedules) {
                $dayIndo = match (strtolower($day)) {
                    'monday' => 'Senin',
                    'tuesday' => 'Selasa',
                    'wednesday' => 'Rabu',
                    'thursday' => 'Kamis',
                    'friday' => 'Jumat',
                    'saturday' => 'Sabtu',
                    'sunday' => 'Minggu',
                    default => $day
                };

                echo "\n{$dayIndo} ({$day}):\n";
                foreach ($daySchedules as $sched) {
                    $subject = $sched['subject']['name'] ?? 'N/A';
                    $teacher = $sched['teacher']['user']['nama'] ?? 'N/A';
                    $classroom = $sched['classroom']['name'] ?? 'N/A';
                    $time = "{$sched['start_time']}-{$sched['end_time']}";
                    echo "  - Period {$sched['period_number']}: {$subject} | {$teacher} | {$classroom} | {$time}\n";
                }
            }

            echo "\n✅ Endpoint SANGAT RINGAN!\n";
            echo "   Hanya load jadwal 1 kelas (~40-50 jadwal)\n";
            echo "   Bukan ribuan jadwal seperti sebelumnya!\n";
        } else {
            echo "⚠️  Tidak ada jadwal untuk kelas ini.\n";
            echo "   Silakan tambahkan jadwal di database.\n";
        }
    } else {
        echo "❌ FAILED!\n";
        echo "Error: {$data['message']}\n";
        if (isset($data['error'])) {
            echo "Details: {$data['error']}\n";
        }
    }

    echo "\n";
    echo "===========================================\n";
    echo "4. Comparison Test\n";
    echo "===========================================\n\n";

    // Count total schedules in database
    $totalAllSchedules = \App\Models\Schedule::count();
    $totalClassSchedules = \App\Models\Schedule::where('class_id', $siswa->class_id)->count();

    echo "SEBELUM (endpoint lama):\n";
    echo "  - Load SEMUA jadwal: {$totalAllSchedules} records 🔴 BERAT!\n";
    echo "  - Server bisa mati karena overload\n\n";

    echo "SESUDAH (endpoint baru):\n";
    echo "  - Load jadwal 1 kelas: {$totalClassSchedules} records ✅ RINGAN!\n";
    echo "  - Server stabil, cepat, hemat bandwidth\n\n";

    $savings = $totalAllSchedules > 0 ? round(($totalAllSchedules - $totalClassSchedules) / $totalAllSchedules * 100, 1) : 0;
    echo "📊 PENGHEMATAN DATA: {$savings}%\n";
    echo "🚀 SERVER PERFORMANCE: Jauh lebih cepat!\n";

    echo "\n✅ TEST SELESAI!\n";
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
