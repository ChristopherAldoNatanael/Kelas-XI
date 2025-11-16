<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\ClassModel;
use App\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "🚀 Creating sample data for your school management system...\n\n";

try {
    DB::beginTransaction();

    // 1. Create sample subjects
    echo "📚 Creating subjects...\n";
    $subjects = [
        ['nama' => 'Matematika', 'kode' => 'MTK'],
        ['nama' => 'Bahasa Indonesia', 'kode' => 'BIND'],
        ['nama' => 'Bahasa Inggris', 'kode' => 'BING'],
        ['nama' => 'Fisika', 'kode' => 'FIS'],
        ['nama' => 'Kimia', 'kode' => 'KIM'],
        ['nama' => 'Biologi', 'kode' => 'BIO'],
        ['nama' => 'Sejarah', 'kode' => 'SEJ'],
        ['nama' => 'Geografi', 'kode' => 'GEO'],
        ['nama' => 'Pemrograman Web', 'kode' => 'PWB'],
        ['nama' => 'Basis Data', 'kode' => 'BD'],
        ['nama' => 'Pemrograman Mobile', 'kode' => 'PM'],
        ['nama' => 'Desain Grafis', 'kode' => 'DG']
    ];

    foreach ($subjects as $subject) {
        Subject::firstOrCreate(['kode' => $subject['kode']], $subject);
    }
    echo "✅ Created " . count($subjects) . " subjects\n";

    // 2. Create sample teachers
    echo "👨‍🏫 Creating teachers...\n";
    $teachers = [
        ['name' => 'Pak Budi Santoso', 'email' => 'budi@sekolah.com', 'mata_pelajaran' => 'Matematika'],
        ['name' => 'Bu Sari Wijaya', 'email' => 'sari@sekolah.com', 'mata_pelajaran' => 'Bahasa Indonesia'],
        ['name' => 'Pak Ahmad Rahman', 'email' => 'ahmad@sekolah.com', 'mata_pelajaran' => 'Bahasa Inggris'],
        ['name' => 'Bu Nina Kusuma', 'email' => 'nina@sekolah.com', 'mata_pelajaran' => 'Fisika'],
        ['name' => 'Pak Doni Pratama', 'email' => 'doni@sekolah.com', 'mata_pelajaran' => 'Pemrograman Web'],
        ['name' => 'Bu Lisa Permata', 'email' => 'lisa@sekolah.com', 'mata_pelajaran' => 'Basis Data'],
        ['name' => 'Pak Rudi Hakim', 'email' => 'rudi@sekolah.com', 'mata_pelajaran' => 'Pemrograman Mobile'],
        ['name' => 'Bu Eka Sari', 'email' => 'eka@sekolah.com', 'mata_pelajaran' => 'Desain Grafis']
    ];

    foreach ($teachers as $teacher) {
        // Create teacher in users table (since guru_id references users table)
        User::firstOrCreate(
            ['email' => $teacher['email']],
            array_merge($teacher, [
                'password' => Hash::make('password123'),
                'role' => 'admin', // Use admin role since there's no 'guru' role
                'is_banned' => false
            ])
        );

        // Also create in teachers table for compatibility
        Teacher::firstOrCreate(
            ['email' => $teacher['email']],
            array_merge($teacher, [
                'password' => Hash::make('password123'),
                'is_banned' => false
            ])
        );
    }
    echo "✅ Created " . count($teachers) . " teachers\n";

    // 3. Create sample classes
    echo "🏫 Creating classes...\n";
    $classes = [
        ['nama_kelas' => 'X RPL 1', 'kode_kelas' => 'X-RPL-1'],
        ['nama_kelas' => 'X RPL 2', 'kode_kelas' => 'X-RPL-2'],
        ['nama_kelas' => 'XI RPL 1', 'kode_kelas' => 'XI-RPL-1'],
        ['nama_kelas' => 'XI RPL 2', 'kode_kelas' => 'XI-RPL-2'],
        ['nama_kelas' => 'XII RPL 1', 'kode_kelas' => 'XII-RPL-1'],
        ['nama_kelas' => 'XII RPL 2', 'kode_kelas' => 'XII-RPL-2'],
        ['nama_kelas' => 'X IPA 1', 'kode_kelas' => 'X-IPA-1'],
        ['nama_kelas' => 'XI IPA 1', 'kode_kelas' => 'XI-IPA-1']
    ];

    foreach ($classes as $class) {
        ClassModel::firstOrCreate(['kode_kelas' => $class['kode_kelas']], $class);
    }
    echo "✅ Created " . count($classes) . " classes\n";

    // 4. Create admin user
    echo "👑 Creating admin user...\n";
    User::firstOrCreate(
        ['email' => 'admin@sekolah.com'],
        [
            'name' => 'Administrator',
            'email' => 'admin@sekolah.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_banned' => false
        ]
    );
    echo "✅ Created admin user (email: admin@sekolah.com, password: admin123)\n";

    // 5. Create sample students
    echo "👨‍🎓 Creating sample students...\n";
    $students = [
        ['name' => 'Andi Pratama', 'email' => 'andi@siswa.com', 'class' => 'XI-RPL-1'],
        ['name' => 'Budi Setiawan', 'email' => 'budi@siswa.com', 'class' => 'XI-RPL-1'],
        ['name' => 'Citra Dewi', 'email' => 'citra@siswa.com', 'class' => 'XI-RPL-2'],
        ['name' => 'Dina Sari', 'email' => 'dina@siswa.com', 'class' => 'XI-RPL-2'],
        ['name' => 'Eko Wahyudi', 'email' => 'eko@siswa.com', 'class' => 'X-RPL-1']
    ];

    foreach ($students as $student) {
        $class = ClassModel::where('kode_kelas', $student['class'])->first();
        User::firstOrCreate(
            ['email' => $student['email']],
            [
                'name' => $student['name'],
                'email' => $student['email'],
                'password' => Hash::make('siswa123'),
                'role' => 'siswa',
                'is_banned' => false
            ]
        );
    }
    echo "✅ Created " . count($students) . " students\n";

    // 6. Create sample schedules
    echo "📅 Creating sample schedules...\n";
    $schedules = [
        ['hari' => 'Senin', 'kelas' => 'XI RPL 1', 'mata_pelajaran' => 'Matematika', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'R101'],
        ['hari' => 'Senin', 'kelas' => 'XI RPL 1', 'mata_pelajaran' => 'Bahasa Indonesia', 'jam_mulai' => '08:30', 'jam_selesai' => '10:00', 'ruang' => 'R101'],
        ['hari' => 'Senin', 'kelas' => 'XI RPL 1', 'mata_pelajaran' => 'Pemrograman Web', 'jam_mulai' => '10:15', 'jam_selesai' => '11:45', 'ruang' => 'Lab Komputer 1'],
        ['hari' => 'Senin', 'kelas' => 'XI RPL 2', 'mata_pelajaran' => 'Basis Data', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'Lab Komputer 2'],
        ['hari' => 'Selasa', 'kelas' => 'XI RPL 1', 'mata_pelajaran' => 'Fisika', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'R102'],
        ['hari' => 'Selasa', 'kelas' => 'XI RPL 1', 'mata_pelajaran' => 'Pemrograman Mobile', 'jam_mulai' => '08:30', 'jam_selesai' => '10:00', 'ruang' => 'Lab Komputer 1'],
        ['hari' => 'Rabu', 'kelas' => 'XI RPL 1', 'mata_pelajaran' => 'Bahasa Inggris', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'R101'],
        ['hari' => 'Rabu', 'kelas' => 'XI RPL 2', 'mata_pelajaran' => 'Desain Grafis', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'Lab Multimedia'],
        ['hari' => 'Kamis', 'kelas' => 'X RPL 1', 'mata_pelajaran' => 'Matematika', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'R103'],
        ['hari' => 'Jumat', 'kelas' => 'XII RPL 1', 'mata_pelajaran' => 'Pemrograman Web', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'Lab Komputer 3']
    ];

    foreach ($schedules as $schedule) {
        // Find teacher by subject in users table (since guru_id references users)
        $teacher = User::where('mata_pelajaran', $schedule['mata_pelajaran'])->first();
        if ($teacher) {
            Schedule::firstOrCreate([
                'hari' => $schedule['hari'],
                'kelas' => $schedule['kelas'],
                'mata_pelajaran' => $schedule['mata_pelajaran'],
                'jam_mulai' => $schedule['jam_mulai']
            ], array_merge($schedule, [
                'guru_id' => $teacher->id
            ]));
        } else {
            echo "⚠️  No teacher found for subject: {$schedule['mata_pelajaran']}\n";
        }
    }
    echo "✅ Created " . count($schedules) . " schedules\n";

    DB::commit();

    echo "\n🎉 SUCCESS! Sample data created successfully!\n\n";
    echo "📊 Summary:\n";
    echo "- " . Subject::count() . " subjects\n";
    echo "- " . Teacher::count() . " teachers\n";
    echo "- " . ClassModel::count() . " classes\n";
    echo "- " . User::count() . " users (including admin and students)\n";
    echo "- " . Schedule::count() . " schedules\n\n";

    echo "🔑 Login credentials:\n";
    echo "Admin: admin@sekolah.com / admin123\n";
    echo "Student: andi@siswa.com / siswa123\n";
    echo "Teacher: budi@sekolah.com / password123\n\n";

    echo "🌐 You can now access your web application and see the data!\n";
} catch (Exception $e) {
    DB::rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
