<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Indonesian teacher names
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
            'user_id' => User::factory()->create([
                'role' => 'kurikulum',
                'nama' => $selectedName
            ])->id,
            'nip' => fake()->unique()->numberBetween(1000000000000000, 9999999999999999),
            'teacher_code' => 'TCH' . fake()->unique()->numberBetween(100, 999),
            'position' => fake()->randomElement(['Guru Senior', 'Guru Madya', 'Guru Utama', 'Kepala Program', 'Koordinator']),
            'department' => fake()->randomElement(['Teknik Informatika', 'Matematika & RPL', 'Bahasa Indonesia', 'Bahasa Inggris & RPL', 'Fisika & RPL', 'Kimia', 'Biologi', 'Sejarah', 'Geografi', 'Ekonomi']),
            'expertise' => fake()->randomElement([
                'Pemrograman Web, Database',
                'Matematika, Kalkulus, Aljabar, Logika',
                'Bahasa Indonesia, Sastra, Linguistik',
                'Bahasa Inggris, TOEFL, IELTS',
                'Fisika, Mekanika, Elektromagnetik',
                'Kimia Organik, Anorganik',
                'Biologi Molekuler, Genetika',
                'Sejarah Indonesia, Sejarah Dunia',
                'Geografi, GIS, Kartografi',
                'Ekonomi Mikro, Makro, Akuntansi'
            ]),
            'certification' => fake()->randomElement([
                'S2 Teknik Informatika',
                'S2 Pendidikan Matematika',
                'S2 Pendidikan Bahasa Indonesia',
                'S2 Pendidikan Bahasa Inggris',
                'S2 Pendidikan Fisika',
                'S2 Pendidikan Kimia',
                'S2 Pendidikan Biologi',
                'S2 Pendidikan Sejarah',
                'S2 Pendidikan Geografi',
                'S2 Pendidikan Ekonomi'
            ]),
            'join_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(['active', 'inactive', 'retired']),
        ];
    }
}
