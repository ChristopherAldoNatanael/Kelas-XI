<?php

/**
 * RUN SEEDERS - Populate database with test data
 */

echo "=== RUNNING DATABASE SEEDERS ===\n";
echo "This will populate tables with test data\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Schedule;
use App\Models\Teacher;

try {
    echo "🔍 Checking database tables exist...\n";

    $tables = DB::select("SHOW TABLES");
    $tableNames = [];
    foreach ($tables as $table) {
        $tableNames[] = current($table);
    }

    $requiredTables = ['users', 'classes', 'subjects', 'teachers', 'schedules'];
    foreach ($requiredTables as $table) {
        if (!in_array($table, $tableNames)) {
            throw new Exception("Table '{$table}' does not exist. Run emergency-db-fix.php first!");
        }
        echo "  ✓ Table '{$table}' exists\n";
    }

    echo "\n🌱 Running User Seeder...\n";
    // Clear existing users and create test users (handle foreign keys)
    DB::statement("SET FOREIGN_KEY_CHECKS = 0");
    DB::table('teacher_attendances')->truncate();
    DB::table('schedules')->truncate();
    DB::table('teachers')->truncate();
    DB::table('users')->truncate();
    DB::statement("SET FOREIGN_KEY_CHECKS = 1");

    User::create([
        'nama' => 'Administrator',
        'email' => 'admin@sekolah.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'status' => 'active'
    ]);

    User::create([
        'nama' => 'Staff Kurikulum',
        'email' => 'kurikulum@sekolah.com',
        'password' => bcrypt('password'),
        'role' => 'kurikulum',
        'status' => 'active'
    ]);

    User::create([
        'nama' => 'Ahmad Siswa',
        'email' => 'siswa@sekolah.com',
        'password' => bcrypt('password'),
        'role' => 'siswa',
        'status' => 'active'
    ]);

    echo "  ✓ Created test users\n";

    echo "\n🏫 Running Class Seeder...\n";
    // Clear existing classes and run ClassSeeder
    DB::table('classes')->truncate();

    $classes = [
        [
            'nama_kelas' => 'X RPL 1',
            'kode_kelas' => 'X-RPL-1',
            'level' => 10,
            'major' => 'Rekayasa Perangkat Lunak',
            'academic_year' => '2024/2025',
            'capacity' => 36,
            'status' => 'active',
        ],
        [
            'nama_kelas' => 'X RPL 2',
            'kode_kelas' => 'X-RPL-2',
            'level' => 10,
            'major' => 'Rekayasa Perangkat Lunak',
            'academic_year' => '2024/2025',
            'capacity' => 36,
            'status' => 'active',
        ],
        [
            'nama_kelas' => 'XI RPL 1',
            'kode_kelas' => 'XI-RPL-1',
            'level' => 11,
            'major' => 'Rekayasa Perangkat Lunak',
            'academic_year' => '2024/2025',
            'capacity' => 36,
            'status' => 'active',
        ],
        [
            'nama_kelas' => 'XI RPL 2',
            'kode_kelas' => 'XI-RPL-2',
            'level' => 11,
            'major' => 'Rekayasa Perangkat Lunak',
            'academic_year' => '2024/2025',
            'capacity' => 36,
            'status' => 'active',
        ],
        [
            'nama_kelas' => 'XII RPL 1',
            'kode_kelas' => 'XII-RPL-1',
            'level' => 12,
            'major' => 'Rekayasa Perangkat Lunak',
            'academic_year' => '2024/2025',
            'capacity' => 36,
            'status' => 'active',
        ],
    ];

    foreach ($classes as $class) {
        ClassModel::create($class);
        echo "  ✓ Created class: {$class['nama_kelas']}\n";
    }

    echo "\n📚 Running Subject Seeder...\n";
    // Clear existing subjects and run SubjectSeeder
    DB::table('subjects')->truncate();

    $subjects = [
        [
            'nama' => 'Matematika',
            'kode' => 'MTK-001',
            'category' => 'wajib',
            'credit_hours' => 4,
            'semester' => 1,
            'status' => 'active'
        ],
        [
            'nama' => 'Bahasa Indonesia',
            'kode' => 'BI-001',
            'category' => 'wajib',
            'credit_hours' => 4,
            'semester' => 1,
            'status' => 'active'
        ],
        [
            'nama' => 'Bahasa Inggris',
            'kode' => 'BING-001',
            'category' => 'wajib',
            'credit_hours' => 2,
            'semester' => 1,
            'status' => 'active'
        ],
        [
            'nama' => 'Pemrograman Dasar',
            'kode' => 'PD-001',
            'category' => 'peminatan',
            'credit_hours' => 4,
            'semester' => 1,
            'status' => 'active'
        ],
        [
            'nama' => 'Basis Data',
            'kode' => 'BD-001',
            'category' => 'peminatan',
            'credit_hours' => 4,
            'semester' => 1,
            'status' => 'active'
        ],
    ];

    foreach ($subjects as $subject) {
        Subject::create($subject);
        echo "  ✓ Created subject: {$subject['nama']}\n";
    }

    echo "\n👨‍🏫 Running Teacher Seeder...\n";
    // Clear existing teachers and run TeacherSeeder
    DB::table('teachers')->truncate();

    // Get first user as teacher reference
    $user = User::first();

    if ($user) {
        Teacher::create([
            'user_id' => $user->id,
            'nip' => '198001011234567890',
            'teacher_code' => 'G001',
            'position' => 'Guru Matematika',
            'department' => 'Matematika',
            'expertise' => 'Matematika',
            'join_date' => '2020-01-01',
            'status' => 'active'
        ]);
        echo "  ✓ Created teacher: {$user->nama}\n";
    }

    echo "\n📅 Running Schedule Seeder...\n";
    // Clear existing schedules and run ScheduleSeeder
    DB::table('schedules')->truncate();

    $teacher = Teacher::first();
    $classes_list = ClassModel::where('major', 'Rekayasa Perangkat Lunak')->take(2)->get();
    $subjects_list = Subject::take(3)->get();

    if ($teacher && count($classes_list) > 0 && count($subjects_list) > 0) {
        $scheduleData = [
            [
                'hari' => 'Senin',
                'kelas' => $classes_list[0]->nama_kelas,
                'mata_pelajaran' => $subjects_list[0]->nama,
                'guru_id' => $teacher->id,
                'jam_mulai' => '07:00',
                'jam_selesai' => '08:30',
                'ruang' => 'R101',
                'status' => 'active'
            ],
            [
                'hari' => 'Selasa',
                'kelas' => $classes_list[0]->nama_kelas,
                'mata_pelajaran' => $subjects_list[1]->nama,
                'guru_id' => $teacher->id,
                'jam_mulai' => '08:30',
                'jam_selesai' => '10:00',
                'ruang' => 'R101',
                'status' => 'active'
            ],
            [
                'hari' => 'Rabu',
                'kelas' => count($classes_list) > 1 ? $classes_list[1]->nama_kelas : $classes_list[0]->nama_kelas,
                'mata_pelajaran' => $subjects_list[2]->nama,
                'guru_id' => $teacher->id,
                'jam_mulai' => '07:00',
                'jam_selesai' => '08:30',
                'ruang' => 'Lab Komputer',
                'status' => 'active'
            ],
        ];

        foreach ($scheduleData as $schedule) {
            Schedule::create($schedule);
            echo "  ✓ Created schedule: {$schedule['hari']} - {$schedule['kelas']} - {$schedule['mata_pelajaran']}\n";
        }
    } else {
        echo "  ⚠️  Skipped schedule creation - missing teacher/class/subject data\n";
    }

    echo "\n🎉 SEEDING COMPLETE!\n";
    echo "===========================\n";
    echo "📊 Database populated with test data:\n";
    echo "👥 Users: " . User::count() . "\n";
    echo "🏫 Classes: " . ClassModel::count() . "\n";
    echo "📚 Subjects: " . Subject::count() . "\n";
    echo "👨‍🏫 Teachers: " . Teacher::count() . "\n";
    echo "📅 Schedules: " . Schedule::count() . "\n\n";

    echo "🔐 LOGIN CREDENTIALS:\n";
    echo "Admin:     admin@sekolah.com / password\n";
    echo "Kurikulum: kurikulum@sekolah.com / password\n";
    echo "Siswa:     siswa@sekolah.com / password\n\n";

    echo "🌐 Access your app: http://127.0.0.1:8000\n";

} catch (Exception $e) {
    echo "\n❌ SEEDING FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
