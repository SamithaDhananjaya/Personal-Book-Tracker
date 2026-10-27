<?php

namespace Database\Factories;

use App\Enums\NoteType;
use App\Models\Book;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'type' => fake()->randomElement(NoteType::cases()),
            'content' => fake()->sentence(12),
            'page' => fake()->optional()->numberBetween(1, 300),
        ];
    }

    /**
     * A quote copied from the book.
     */
    public function quote(): static
    {
        return $this->state(fn (): array => [
            'type' => NoteType::Quote,
        ]);
    }
}
