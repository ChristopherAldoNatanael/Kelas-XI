<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ClassModel;

class ClassesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            // X RPL
            [
                'nama_kelas' => 'X RPL 1',
                'kode_kelas' => 'XRPL1',
                'level' => 10,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'capacity' => 36,
                'status' => 'active'
            ],
            [
                'nama_kelas' => 'X RPL 2',
                'kode_kelas' => 'XRPL2',
                'level' => 10,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'capacity' => 36,
                'status' => 'active'
            ],
            // XI RPL
            [
                'nama_kelas' => 'XI RPL 1',
                'kode_kelas' => 'XIRPL1',
                'level' => 11,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'capacity' => 36,
                'status' => 'active'
            ],
            [
                'nama_kelas' => 'XI RPL 2',
                'kode_kelas' => 'XIRPL2',
                'level' => 11,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'capacity' => 36,
                'status' => 'active'
            ],
            // XII RPL
            [
                'nama_kelas' => 'XII RPL 1',
                'kode_kelas' => 'XIIRPL1',
                'level' => 12,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'capacity' => 36,
                'status' => 'active'
            ],
            [
                'nama_kelas' => 'XII RPL 2',
                'kode_kelas' => 'XIIRPL2',
                'level' => 12,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'capacity' => 36,
                'status' => 'active'
            ],
        ];

        foreach ($classes as $class) {
            ClassModel::firstOrCreate(
                ['nama_kelas' => $class['nama_kelas']],
                $class
            );
        }
    }
}
