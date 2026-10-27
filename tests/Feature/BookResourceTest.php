<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Filament\Resources\Books\BookResource;
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

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_books_table_lists_books_with_author_names(): void
    {
        $books = Book::factory(3)->for($this->user)->create();

        Livewire::test(ListBooks::class)
            ->assertCanSeeTableRecords($books)
            ->assertSee($books->first()->author->name);
    }

    public function test_books_table_can_be_filtered_by_status(): void
    {
        $reading = Book::factory()->reading()->for($this->user)->create();
        $wantToRead = Book::factory()->for($this->user)->create();

        Livewire::test(ListBooks::class)
            ->filterTable('status', BookStatus::Reading->value)
            ->assertCanSeeTableRecords([$reading])
            ->assertCanNotSeeTableRecords([$wantToRead]);
    }

    public function test_book_can_be_created(): void
    {
        $author = Author::factory()->for($this->user)->create();

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
            'user_id' => $this->user->id,
        ]);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Dune',
                'author_id' => Author::factory()->for($this->user)->create()->id,
                'genre' => 'sci_fi',
                'status' => 'completed',
                'rating' => 6,
            ])
            ->call('create')
            ->assertHasFormErrors(['rating' => 'max']);
    }

    public function test_books_of_other_users_are_hidden(): void
    {
        $ownBook = Book::factory()->for($this->user)->create();
        $otherBook = Book::factory()->create();

        Livewire::test(ListBooks::class)
            ->assertCanSeeTableRecords([$ownBook])
            ->assertCanNotSeeTableRecords([$otherBook]);

        $this->get(BookResource::getUrl('view', ['record' => $otherBook]))->assertNotFound();
        $this->get(BookResource::getUrl('edit', ['record' => $otherBook]))->assertNotFound();
    }

    public function test_book_cannot_be_assigned_to_another_users_author(): void
    {
        $otherAuthor = Author::factory()->create();

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Dune',
                'author_id' => $otherAuthor->id,
                'genre' => 'sci_fi',
                'status' => 'want_to_read',
            ])
            ->call('create')
            ->assertHasFormErrors(['author_id']);
    }
}
