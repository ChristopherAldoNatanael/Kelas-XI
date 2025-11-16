<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Subject;
use App\Models\Teacher;

class SubjectTeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mapping guru dengan mata pelajaran berdasarkan expertise mereka
        $subjectTeacherMappings = [
            // Budi Santoso - Matematika & RPL
            'TCH002' => [
                'subjects' => ['Matematika'],
                'primary' => ['Matematika']
            ],
            // Siti Nurhaliza - Bahasa Indonesia
            'TCH003' => [
                'subjects' => ['Bahasa Indonesia'],
                'primary' => ['Bahasa Indonesia']
            ],
            // Adi Wijaya - Bahasa Inggris & RPL
            'TCH004' => [
                'subjects' => ['Bahasa Inggris'],
                'primary' => ['Bahasa Inggris']
            ],
            // Maya Sari - Fisika & RPL
            'TCH005' => [
                'subjects' => ['Fisika'],
                'primary' => ['Fisika']
            ],
            // Rizki Ramadhan - RPL Senior
            'TCH006' => [
                'subjects' => ['Rekayasa Perangkat Lunak', 'Pemrograman Dasar', 'Pemrograman Web'],
                'primary' => ['Rekayasa Perangkat Lunak', 'Pemrograman Dasar']
            ],
            // Diana Putri - Database & RPL
            'TCH007' => [
                'subjects' => ['Database', 'Pemrograman Dasar'],
                'primary' => ['Database']
            ],
            // Eko Prasetyo - Mobile Programming
            'TCH008' => [
                'subjects' => ['Pemrograman Mobile', 'Pemrograman Dasar'],
                'primary' => ['Pemrograman Mobile']
            ],
            // Lisa Permata - UI/UX & Design
            'TCH009' => [
                'subjects' => ['UI/UX Design'],
                'primary' => ['UI/UX Design']
            ],
            // Hendra Gunawan - Network & RPL
            'TCH010' => [
                'subjects' => ['Jaringan Komputer'],
                'primary' => ['Jaringan Komputer']
            ],
        ];

        foreach ($subjectTeacherMappings as $teacherCode => $mapping) {
            $teacher = Teacher::where('teacher_code', $teacherCode)->first();

            if (!$teacher) {
                continue; // Skip jika teacher tidak ditemukan
            }

            foreach ($mapping['subjects'] as $subjectName) {
                $subject = Subject::where('name', $subjectName)->first();

                if ($subject) {
                    $isPrimary = in_array($subjectName, $mapping['primary']);

                    // Attach subject ke teacher dengan pivot data
                    $teacher->subjects()->syncWithoutDetaching([
                        $subject->id => ['is_primary' => $isPrimary]
                    ]);
                }
            }
        }

        // Tambahkan guru untuk mata pelajaran wajib lainnya jika belum ada
        $additionalMappings = [
            'Kimia' => 'TCH005', // Maya Sari (Fisika guru bisa mengajar Kimia juga)
            'Biologi' => 'TCH005', // Maya Sari
            'Pendidikan Kewarganegaraan' => 'TCH003', // Siti Nurhaliza
            'Pendidikan Agama Islam' => 'TCH003', // Siti Nurhaliza
        ];

        foreach ($additionalMappings as $subjectName => $teacherCode) {
            $teacher = Teacher::where('teacher_code', $teacherCode)->first();
            $subject = Subject::where('name', $subjectName)->first();

            if ($teacher && $subject) {
                // Attach sebagai guru non-primary (bantuan)
                $teacher->subjects()->syncWithoutDetaching([
                    $subject->id => ['is_primary' => false]
                ]);
            }
        }
    }
}
