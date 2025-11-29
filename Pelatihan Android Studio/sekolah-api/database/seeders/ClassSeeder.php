<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ClassModel;

class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            [
                'name' => 'X RPL 1',
                'level' => 10,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'homeroom_teacher_id' => null,
                'capacity' => 36,
                'status' => 'active',
            ],
            [
                'name' => 'X RPL 2',
                'level' => 10,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'homeroom_teacher_id' => null,
                'capacity' => 36,
                'status' => 'active',
            ],
            [
                'name' => 'XI RPL 1',
                'level' => 11,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'homeroom_teacher_id' => null,
                'capacity' => 36,
                'status' => 'active',
            ],
            [
                'name' => 'XI RPL 2',
                'level' => 11,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'homeroom_teacher_id' => null,
                'capacity' => 36,
                'status' => 'active',
            ],
            [
                'name' => 'XII RPL 1',
                'level' => 12,
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'homeroom_teacher_id' => null,
                'capacity' => 36,
                'status' => 'active',
            ],
        ];

        foreach ($classes as $class) {
            ClassModel::create($class);
        }
    }
}
