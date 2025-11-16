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
        // Get existing users or create new ones
        $adminUser = User::where('email', 'admin@gmail.com')->first();
        $testUser = User::where('email', 'test@example.com')->first();

        if ($adminUser && !Teacher::where('user_id', $adminUser->id)->exists()) {
            Teacher::create([
                'user_id' => $adminUser->id,
                'nip' => '198001012010011001',
                'teacher_code' => 'TCH001',
                'position' => 'Guru Senior',
                'department' => 'Teknik Informatika',
                'expertise' => 'Pemrograman Web, Database',
                'certification' => 'Certified Laravel Developer',
                'join_date' => '2020-01-01',
                'status' => 'active'
            ]);
        }

        // Create additional teachers with SMK RPL expertise
        $teachers = [
            [
                'nama' => 'Budi Santoso',
                'email' => 'budi.santoso@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '198502152015032001',
                    'teacher_code' => 'TCH002',
                    'position' => 'Guru Matematika & RPL',
                    'department' => 'Matematika & RPL',
                    'expertise' => 'Matematika, Kalkulus, Aljabar, Logika Programming',
                    'certification' => 'Certified Math & Programming Teacher',
                    'join_date' => '2019-03-15',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '198703202018011002',
                    'teacher_code' => 'TCH003',
                    'position' => 'Guru Bahasa Indonesia',
                    'department' => 'Bahasa Indonesia',
                    'expertise' => 'Bahasa Indonesia, Sastra, Linguistik, Penulisan Teknis',
                    'certification' => 'Certified Indonesian Language Teacher',
                    'join_date' => '2018-07-10',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Adi Wijaya',
                'email' => 'adi.wijaya@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '198904252019022003',
                    'teacher_code' => 'TCH004',
                    'position' => 'Guru Bahasa Inggris & RPL',
                    'department' => 'Bahasa Inggris & RPL',
                    'expertise' => 'Bahasa Inggris, TOEFL, IELTS, Technical English, Programming English',
                    'certification' => 'Certified English & Programming Teacher',
                    'join_date' => '2019-08-20',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Maya Sari',
                'email' => 'maya.sari@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '199005302020013004',
                    'teacher_code' => 'TCH005',
                    'position' => 'Guru Fisika & RPL',
                    'department' => 'Fisika & RPL',
                    'expertise' => 'Fisika, Mekanika, Elektromagnetik, Fisika Komputasi',
                    'certification' => 'Certified Physics & Computing Teacher',
                    'join_date' => '2020-02-14',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Rizki Ramadhan',
                'email' => 'rizki.ramadhan@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '199106102021014005',
                    'teacher_code' => 'TCH006',
                    'position' => 'Guru RPL Senior',
                    'department' => 'Rekayasa Perangkat Lunak',
                    'expertise' => 'Pemrograman Web, Database, Mobile App, Laravel, React, Flutter',
                    'certification' => 'Certified Full-Stack Developer & Teacher',
                    'join_date' => '2021-01-15',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Diana Putri',
                'email' => 'diana.putri@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '199207202022015006',
                    'teacher_code' => 'TCH007',
                    'position' => 'Guru Basis Data & RPL',
                    'department' => 'Basis Data & RPL',
                    'expertise' => 'Database, SQL, MySQL, PostgreSQL, Database Design, Data Modeling',
                    'certification' => 'Certified Database Administrator & Teacher',
                    'join_date' => '2022-03-10',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Eko Prasetyo',
                'email' => 'eko.prasetyo@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '199308252023016007',
                    'teacher_code' => 'TCH008',
                    'position' => 'Guru Mobile Programming',
                    'department' => 'Mobile Programming',
                    'expertise' => 'Android, iOS, Flutter, React Native, Kotlin, Swift',
                    'certification' => 'Certified Mobile App Developer & Teacher',
                    'join_date' => '2023-05-20',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Lisa Permata',
                'email' => 'lisa.permata@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '199409152024017008',
                    'teacher_code' => 'TCH009',
                    'position' => 'Guru UI/UX & Design',
                    'department' => 'Design & User Experience',
                    'expertise' => 'UI/UX Design, Graphic Design, Adobe Creative Suite, Figma, Prototyping',
                    'certification' => 'Certified UI/UX Designer & Teacher',
                    'join_date' => '2024-01-10',
                    'status' => 'active'
                ]
            ],
            [
                'nama' => 'Hendra Gunawan',
                'email' => 'hendra.gunawan@school.com',
                'password' => bcrypt('password'),
                'role' => 'kurikulum',
                'teacher_data' => [
                    'nip' => '199510202025018009',
                    'teacher_code' => 'TCH010',
                    'position' => 'Guru Jaringan & RPL',
                    'department' => 'Network & RPL',
                    'expertise' => 'Computer Network, Cisco, Network Security, Cloud Computing, DevOps',
                    'certification' => 'Certified Network Engineer & Teacher',
                    'join_date' => '2025-02-15',
                    'status' => 'active'
                ]
            ]
        ];

        foreach ($teachers as $teacherData) {
            // Check if user already exists
            $user = User::where('email', $teacherData['email'])->first();
            if (!$user) {
                $user = User::create([
                    'nama' => $teacherData['nama'],
                    'email' => $teacherData['email'],
                    'password' => $teacherData['password'],
                    'role' => $teacherData['role'],
                    'status' => 'active'
                ]);
            }

            // Check if teacher already exists for this user
            if (!Teacher::where('user_id', $user->id)->exists()) {
                Teacher::create(array_merge($teacherData['teacher_data'], [
                    'user_id' => $user->id
                ]));
            }
        }
    }
}
