<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Room ' . fake()->numberBetween(100, 999),
            'code' => 'R' . fake()->unique()->numberBetween(100, 999),
            'type' => fake()->randomElement(['regular', 'laboratory', 'special', 'hall']),
            'capacity' => fake()->numberBetween(20, 100),
            'building' => fake()->randomElement(['Building A', 'Building B', 'Building C', 'Main Building']),
            'floor' => fake()->numberBetween(1, 5),
            'facilities' => [
                'Projector' => 'Available',
                'Whiteboard' => 'Available',
                'Air Conditioning' => 'Available',
            ],
            'status' => fake()->randomElement(['available', 'maintenance', 'unavailable']),
        ];
    }
}
