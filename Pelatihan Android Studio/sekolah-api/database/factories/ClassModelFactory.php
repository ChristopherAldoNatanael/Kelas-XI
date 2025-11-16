<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassModel>
 */
class ClassModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Indonesian teacher names for homeroom teachers
        $indonesianNames = [
            'Ahmad Fauzi', 'Siti Nurhaliza', 'Budi Santoso', 'Maya Sari', 'Rudi Hartono',
            'Dewi Lestari', 'Agus Setiawan', 'Rina Marlina', 'Eko Prasetyo', 'Lina Susanti',
            'Hendra Wijaya', 'Tuti Rahayu', 'Dedi Kurniawan', 'Sri Wahyuni', 'Andi Rahman',
            'Nurul Hidayah', 'Yusuf Abdullah', 'Ratna Sari', 'Fajar Nugroho', 'Indah Permata',
            'Rizki Ramadhan', 'Aulia Rahman', 'Bayu Saputra', 'Cici Amelia', 'Doni Setiawan',
            'Eka Putri', 'Firman Hidayat', 'Gita Lestari', 'Hadi Sucipto', 'Ika Nurhayati',
            'Joko Susanto', 'Kiki Amalia', 'Luki Hermawan', 'Mira Sari', 'Nanda Putra',
            'Oki Setiawan', 'Putri Maharani', 'Qori Ramadhan', 'Rina Susanti', 'Sandi Wijaya',
            'Tika Lestari', 'Umar Abdullah', 'Vina Sari', 'Wahyu Nugroho', 'Yuni Astuti',
            'Zaki Rahman', 'Ani Susanti', 'Beni Setiawan', 'Citra Lestari', 'Dani Hermawan'
        ];

        $selectedName = fake()->randomElement($indonesianNames);

        return [
            'name' => fake()->randomElement(['X', 'XI', 'XII']) . '-' . fake()->randomElement(['RPL', 'TKJ', 'MM', 'BD']) . '-' . fake()->numberBetween(1, 5),
            'level' => fake()->numberBetween(10, 12),
            'major' => fake()->randomElement(['RPL', 'TKJ', 'MM', 'BD', 'OTKP']),
            'academic_year' => fake()->year() . '/' . (fake()->year() + 1),
            'homeroom_teacher_id' => User::factory()->create([
                'role' => 'kurikulum',
                'nama' => $selectedName
            ])->id,
            'capacity' => fake()->numberBetween(20, 40),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
