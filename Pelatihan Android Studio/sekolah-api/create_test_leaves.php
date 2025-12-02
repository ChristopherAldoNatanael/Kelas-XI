<?php

/**
 * Script untuk membuat leave baru untuk testing
 * Guru di X RPL 2 hari Selasa: Sari Dewi (17), Joko Susilo (18)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use App\Models\Leave;
use App\Models\Teacher;

$today = Carbon::now()->format('Y-m-d');

echo "=== Membuat Leave untuk Testing ===\n\n";
echo "Tanggal: $today\n\n";

// Buat leave untuk Sari Dewi (ID: 17) - mengajar Matematika
$leave1 = Leave::updateOrCreate(
    [
        'teacher_id' => 17,
        'start_date' => $today,
        'end_date' => $today,
    ],
    [
        'reason' => 'sakit',
        'custom_reason' => null,
        'status' => 'approved',
        'notes' => 'Testing izin untuk demo',
        'substitute_teacher_id' => 3, // Budi Santoso sebagai pengganti
        'approved_by' => 1,
        'approved_at' => Carbon::now(),
    ]
);

$teacher1 = Teacher::find(17);
$substitute1 = Teacher::find(3);
echo "✓ Leave #1 dibuat:\n";
echo "  Guru: {$teacher1->nama} (ID: 17)\n";
echo "  Pengganti: {$substitute1->nama} (ID: 3)\n";
echo "  Tanggal: $today\n";
echo "  Status: approved\n";
echo "  Alasan: sakit\n\n";

// Buat leave untuk Andi Pratama (ID: 20) - mengajar Pemrograman Dasar
$leave2 = Leave::updateOrCreate(
    [
        'teacher_id' => 20,
        'start_date' => $today,
        'end_date' => $today,
    ],
    [
        'reason' => 'urusan_keluarga',
        'custom_reason' => null,
        'status' => 'approved',
        'notes' => 'Testing izin untuk demo',
        'substitute_teacher_id' => 4, // Siti Aminah sebagai pengganti
        'approved_by' => 1,
        'approved_at' => Carbon::now(),
    ]
);

$teacher2 = Teacher::find(20);
$substitute2 = Teacher::find(4);
echo "✓ Leave #2 dibuat:\n";
echo "  Guru: {$teacher2->nama} (ID: 20)\n";
echo "  Pengganti: {$substitute2->nama} (ID: 4)\n";
echo "  Tanggal: $today\n";
echo "  Status: approved\n";
echo "  Alasan: urusan_keluarga\n\n";

// Verifikasi
echo "=== Verifikasi Leaves Aktif Hari Ini ===\n\n";

$activeLeaves = Leave::where('status', 'approved')
    ->where('start_date', '<=', $today)
    ->where('end_date', '>=', $today)
    ->get();

echo "Total approved leaves aktif: {$activeLeaves->count()}\n\n";

foreach ($activeLeaves as $leave) {
    $t = Teacher::find($leave->teacher_id);
    $s = Teacher::find($leave->substitute_teacher_id);
    echo "- {$t->nama} ({$leave->reason})";
    if ($s) {
        echo " -> Diganti oleh: {$s->nama}";
    }
    echo "\n";
}

echo "\n✅ Selesai! Coba refresh halaman jadwal siswa untuk X RPL 2.\n";
