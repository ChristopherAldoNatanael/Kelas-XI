<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Classroom;

class ClassroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classrooms = [
            [
                'name' => 'Laboratorium Komputer 1',
                'code' => 'LAB1',
                'type' => 'laboratory',
                'capacity' => 40,
                'floor' => 1,
                'building' => 'Gedung A',
                'facilities' => ['AC', 'Proyektor', 'Komputer', 'Internet', 'Sound System'],
                'status' => 'available',
            ],
            [
                'name' => 'Laboratorium Komputer 2',
                'code' => 'LAB2',
                'type' => 'laboratory',
                'capacity' => 40,
                'floor' => 2,
                'building' => 'Gedung A',
                'facilities' => ['AC', 'Proyektor', 'Komputer', 'Internet'],
                'status' => 'available',
            ],
            [
                'name' => 'Ruang Kelas X RPL A',
                'code' => 'XRA',
                'type' => 'regular',
                'capacity' => 36,
                'floor' => 1,
                'building' => 'Gedung B',
                'facilities' => ['AC', 'Kipas Angin', 'Papan Tulis'],
                'status' => 'available',
            ],
            [
                'name' => 'Ruang Kelas X RPL B',
                'code' => 'XRB',
                'type' => 'regular',
                'capacity' => 36,
                'floor' => 1,
                'building' => 'Gedung B',
                'facilities' => ['AC', 'Kipas Angin', 'Papan Tulis'],
                'status' => 'available',
            ],
            [
                'name' => 'Ruang Kelas XI RPL A',
                'code' => 'XIRA',
                'type' => 'regular',
                'capacity' => 36,
                'floor' => 2,
                'building' => 'Gedung B',
                'facilities' => ['AC', 'Kipas Angin', 'Papan Tulis', 'Proyektor'],
                'status' => 'available',
            ],
            [
                'name' => 'Ruang Kelas XI RPL B',
                'code' => 'XIRB',
                'type' => 'regular',
                'capacity' => 36,
                'floor' => 2,
                'building' => 'Gedung B',
                'facilities' => ['AC', 'Kipas Angin', 'Papan Tulis', 'Proyektor'],
                'status' => 'available',
            ],
            [
                'name' => 'Ruang Kelas XII RPL A',
                'code' => 'XIIRA',
                'type' => 'regular',
                'capacity' => 36,
                'floor' => 3,
                'building' => 'Gedung B',
                'facilities' => ['AC', 'Kipas Angin', 'Papan Tulis', 'Proyektor'],
                'status' => 'available',
            ],
            [
                'name' => 'Aula Serbaguna',
                'code' => 'AULA',
                'type' => 'hall',
                'capacity' => 200,
                'floor' => 1,
                'building' => 'Gedung C',
                'facilities' => ['AC', 'Sound System', 'Panggung', 'Lampu', 'Kursi Lipat'],
                'status' => 'available',
            ],
            [
                'name' => 'Ruang Musik',
                'code' => 'MUSIK',
                'type' => 'special',
                'capacity' => 50,
                'floor' => 1,
                'building' => 'Gedung C',
                'facilities' => ['AC', 'Sound System', 'Piano', 'Gitar', 'Drum'],
                'status' => 'available',
            ],
            [
                'name' => 'Perpustakaan',
                'code' => 'PERPUS',
                'type' => 'special',
                'capacity' => 80,
                'floor' => 2,
                'building' => 'Gedung C',
                'facilities' => ['AC', 'Komputer', 'Internet', 'Rak Buku', 'Meja Baca'],
                'status' => 'available',
            ],
        ];

        foreach ($classrooms as $classroom) {
            Classroom::firstOrCreate(['code' => $classroom['code']], $classroom);
        }
    }
}
