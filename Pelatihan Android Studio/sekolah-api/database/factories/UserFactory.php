<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Indonesian names for different roles
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
            'name' => $selectedName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(['admin', 'kurikulum', 'siswa', 'kepala-sekolah']),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
