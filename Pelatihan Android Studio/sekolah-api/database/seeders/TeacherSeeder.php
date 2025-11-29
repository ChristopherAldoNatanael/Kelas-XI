<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Teacher;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create teachers directly without user association
        $teachers = [
            [
                'nama' => 'Dr. Ahmad Santoso',
                'nip' => '198001012010011001',
                'teacher_code' => 'TCH001',
                'position' => 'Guru Senior',
                'department' => 'Teknik Informatika',
                'expertise' => 'Pemrograman Web, Database',
                'certification' => 'Certified Laravel Developer',
                'join_date' => '2020-01-01',
                'status' => 'active'
            ],
            [
                'nama' => 'Budi Santoso',
                'nip' => '198502152015032001',
                'teacher_code' => 'TCH002',
                'position' => 'Guru Matematika & RPL',
                'department' => 'Matematika & RPL',
                'expertise' => 'Matematika, Kalkulus, Aljabar, Logika Programming',
                'certification' => 'Certified Math & Programming Teacher',
                'join_date' => '2019-03-15',
                'status' => 'active'
            ],
            [
                'nama' => 'Siti Nurhaliza',
                'nip' => '198703202018011002',
                'teacher_code' => 'TCH003',
                'position' => 'Guru Bahasa Indonesia',
                'department' => 'Bahasa Indonesia',
                'expertise' => 'Bahasa Indonesia, Sastra, Linguistik, Penulisan Teknis',
                'certification' => 'Certified Indonesian Language Teacher',
                'join_date' => '2018-07-10',
                'status' => 'active'
            ],
            [
                'nama' => 'Adi Wijaya',
                'nip' => '198904252019022003',
                'teacher_code' => 'TCH004',
                'position' => 'Guru Bahasa Inggris & RPL',
                'department' => 'Bahasa Inggris & RPL',
                'expertise' => 'Bahasa Inggris, TOEFL, IELTS, Technical English, Programming English',
                'certification' => 'Certified English & Programming Teacher',
                'join_date' => '2019-08-20',
                'status' => 'active'
            ],
            [
                'nama' => 'Maya Sari',
                'nip' => '199005302020013004',
                'teacher_code' => 'TCH005',
                'position' => 'Guru Fisika & RPL',
                'department' => 'Fisika & RPL',
                'expertise' => 'Fisika, Mekanika, Elektromagnetik, Fisika Komputasi',
                'certification' => 'Certified Physics & Computing Teacher',
                'join_date' => '2020-02-14',
                'status' => 'active'
            ],
            [
                'nama' => 'Rizki Ramadhan',
                'nip' => '199106102021014005',
                'teacher_code' => 'TCH006',
                'position' => 'Guru RPL Senior',
                'department' => 'Rekayasa Perangkat Lunak',
                'expertise' => 'Pemrograman Web, Database, Mobile App, Laravel, React, Flutter',
                'certification' => 'Certified Full-Stack Developer & Teacher',
                'join_date' => '2021-01-15',
                'status' => 'active'
            ],
            [
                'nama' => 'Diana Putri',
                'nip' => '199207202022015006',
                'teacher_code' => 'TCH007',
                'position' => 'Guru Basis Data & RPL',
                'department' => 'Basis Data & RPL',
                'expertise' => 'Database, SQL, MySQL, PostgreSQL, Database Design, Data Modeling',
                'certification' => 'Certified Database Administrator & Teacher',
                'join_date' => '2022-03-10',
                'status' => 'active'
            ],
            [
                'nama' => 'Eko Prasetyo',
                'nip' => '199308252023016007',
                'teacher_code' => 'TCH008',
                'position' => 'Guru Mobile Programming',
                'department' => 'Mobile Programming',
                'expertise' => 'Android, iOS, Flutter, React Native, Kotlin, Swift',
                'certification' => 'Certified Mobile App Developer & Teacher',
                'join_date' => '2023-05-20',
                'status' => 'active'
            ],
            [
                'nama' => 'Lisa Permata',
                'nip' => '199409152024017008',
                'teacher_code' => 'TCH009',
                'position' => 'Guru UI/UX & Design',
                'department' => 'Design & User Experience',
                'expertise' => 'UI/UX Design, Graphic Design, Adobe Creative Suite, Figma, Prototyping',
                'certification' => 'Certified UI/UX Designer & Teacher',
                'join_date' => '2024-01-10',
                'status' => 'active'
            ],
            [
                'nama' => 'Hendra Gunawan',
                'nip' => '199510202025018009',
                'teacher_code' => 'TCH010',
                'position' => 'Guru Jaringan & RPL',
                'department' => 'Network & RPL',
                'expertise' => 'Computer Network, Cisco, Network Security, Cloud Computing, DevOps',
                'certification' => 'Certified Network Engineer & Teacher',
                'join_date' => '2025-02-15',
                'status' => 'active'
            ]
        ];

        foreach ($teachers as $teacherData) {
            // Check if teacher already exists by NIP
            if (!Teacher::where('nip', $teacherData['nip'])->exists()) {
                Teacher::create($teacherData);
            }
        }
    }
}
