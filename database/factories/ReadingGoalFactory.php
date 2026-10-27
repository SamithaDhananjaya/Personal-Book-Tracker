<?php

namespace Database\Factories;

use App\Models\ReadingGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingGoal>
 */
class ReadingGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => now()->year,
            'target_books' => fake()->numberBetween(6, 52),
        ];
    }
}
