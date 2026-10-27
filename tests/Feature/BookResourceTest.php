<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_books_table_lists_books_with_author_names(): void
    {
        $books = Book::factory(3)->create();

        Livewire::test(ListBooks::class)
            ->assertCanSeeTableRecords($books)
            ->assertSee($books->first()->author->name);
    }

    public function test_books_table_can_be_filtered_by_status(): void
    {
        $reading = Book::factory()->reading()->create();
        $wantToRead = Book::factory()->create();

        Livewire::test(ListBooks::class)
            ->filterTable('status', BookStatus::Reading->value)
            ->assertCanSeeTableRecords([$reading])
            ->assertCanNotSeeTableRecords([$wantToRead]);
    }

    public function test_book_can_be_created(): void
    {
        $author = Author::factory()->create();

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Dune',
                'author_id' => $author->id,
                'genre' => 'sci_fi',
                'status' => 'want_to_read',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Book::class, [
            'title' => 'Dune',
            'author_id' => $author->id,
        ]);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Dune',
                'author_id' => Author::factory()->create()->id,
                'genre' => 'sci_fi',
                'status' => 'completed',
                'rating' => 6,
            ])
            ->call('create')
            ->assertHasFormErrors(['rating' => 'max']);
    }
}
