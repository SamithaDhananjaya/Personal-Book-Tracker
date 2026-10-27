<?php

namespace Database\Factories;

use App\Models\Shelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shelf>
 */
class ShelfFactory extends Factory
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
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'color' => fake()->randomElement(array_keys(Shelf::COLORS)),
        ];
    }
}
