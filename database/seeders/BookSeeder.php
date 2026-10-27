<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $shelves = Shelf::factory(3)->for($user)->create();

        Book::factory(8)->for($user)->create();
        Book::factory(4)->reading()->for($user)->create();
        Book::factory(12)->completed()->for($user)->create();

        $user->books->each(fn (Book $book) => $book->shelves()->attach(
            $shelves->random(fake()->numberBetween(0, 2))->modelKeys(),
        ));
    }
}
