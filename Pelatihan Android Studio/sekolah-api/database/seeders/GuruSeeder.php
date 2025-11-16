<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Guru;

class GuruSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gurus = [
            [
                'kode' => 'GUR001',
                'nama_guru' => 'Ahmad Surya Wijaya',
                'telepon' => '081234567890',
            ],
            [
                'kode' => 'GUR002',
                'nama_guru' => 'Siti Nurhaliza',
                'telepon' => '081298765432',
            ],
            [
                'kode' => 'GUR003',
                'nama_guru' => 'Budi Santoso',
                'telepon' => '081345678901',
            ],
            [
                'kode' => 'GUR004',
                'nama_guru' => 'Dewi Lestari',
                'telepon' => null,
            ],
            [
                'kode' => 'GUR005',
                'nama_guru' => 'Eko Prasetyo',
                'telepon' => '081456789012',
            ],
            [
                'kode' => 'GUR006',
                'nama_guru' => 'Fitriani Sari',
                'telepon' => '081567890123',
            ],
            [
                'kode' => 'GUR007',
                'nama_guru' => 'Guntur Wicaksono',
                'telepon' => null,
            ],
            [
                'kode' => 'GUR008',
                'nama_guru' => 'Hana Permata',
                'telepon' => '081678901234',
            ],
        ];

        foreach ($gurus as $guru) {
            Guru::firstOrCreate(['kode' => $guru['kode']], $guru);
        }
    }
}
