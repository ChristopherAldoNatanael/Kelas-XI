<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->lexify('???')) . fake()->numberBetween(100, 999),
            'category' => fake()->randomElement(['wajib', 'peminatan', 'mulok']),
            'description' => fake()->sentence(),
            'credit_hours' => fake()->numberBetween(1, 4),
            'semester' => fake()->numberBetween(1, 8),
            'status' => 'active',
        ];
    }
}
