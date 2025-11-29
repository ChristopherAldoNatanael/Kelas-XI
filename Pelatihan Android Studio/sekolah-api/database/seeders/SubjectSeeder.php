<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['nama' => 'Matematika', 'kode' => 'MAT'],
            ['nama' => 'Bahasa Indonesia', 'kode' => 'IND'],
            ['nama' => 'Bahasa Inggris', 'kode' => 'ENG'],
            ['nama' => 'Pemrograman Dasar', 'kode' => 'PROG'],
            ['nama' => 'Fisika', 'kode' => 'FIS'],
            ['nama' => 'Kimia', 'kode' => 'KIM'],
            ['nama' => 'Biologi', 'kode' => 'BIO'],
            ['nama' => 'Rekayasa Perangkat Lunak', 'kode' => 'RPL'],
            ['nama' => 'Pemrograman Web', 'kode' => 'WEB'],
            ['nama' => 'Pemrograman Mobile', 'kode' => 'MOBILE'],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(['kode' => $subject['kode']], $subject);
        }

        $this->command->info('Subjects seeded successfully!');
    }
}
