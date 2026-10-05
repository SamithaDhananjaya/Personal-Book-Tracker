<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Book::factory(8)->create();
        Book::factory(4)->reading()->create();
        Book::factory(12)->completed()->create();
    }
}
