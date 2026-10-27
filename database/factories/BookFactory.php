<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim($this->faker->sentence(3), '.'),
            'user_id' => User::factory(),
            'author_id' => fn (array $attributes): Author => Author::factory()->create([
                'user_id' => $attributes['user_id'],
            ]),
            'genre' => $this->faker->randomElement(Genre::cases()),
            'status' => BookStatus::WantToRead,
            'rating' => null,
            'summary' => $this->faker->paragraph(),
            'read_at' => null,
        ];
    }

    /**
     * A book the reader is partway through.
     */
    public function reading(): static
    {
        return $this->state(fn (): array => [
            'status' => BookStatus::Reading,
        ]);
    }

    /**
     * A finished book, which is the only state carrying a rating and a read date.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => BookStatus::Completed,
            'rating' => $this->faker->numberBetween(1, 5),
            'read_at' => $this->faker->dateTimeBetween('-1 year'),
        ]);
    }
}
