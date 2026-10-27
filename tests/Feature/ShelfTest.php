<?php

namespace Tests\Feature;

use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Shelves\Pages\ManageShelves;
use App\Models\Author;
use App\Models\Book;
use App\Models\Shelf;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShelfTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_shelf_can_be_created_for_the_signed_in_user(): void
    {
        Livewire::test(ManageShelves::class)
            ->callAction(CreateAction::class, ['name' => 'Favorites', 'color' => 'danger'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Shelf::class, [
            'user_id' => $this->user->id,
            'name' => 'Favorites',
        ]);
    }

    public function test_shelf_names_are_unique_per_user(): void
    {
        Shelf::factory()->for($this->user)->create(['name' => 'Favorites']);
        Shelf::factory()->create(['name' => 'Book club']);

        Livewire::test(ManageShelves::class)
            ->callAction(CreateAction::class, ['name' => 'Favorites', 'color' => 'gray'])
            ->assertHasFormErrors(['name' => 'unique']);

        Livewire::test(ManageShelves::class)
            ->callAction(CreateAction::class, ['name' => 'Book club', 'color' => 'gray'])
            ->assertHasNoFormErrors();
    }

    public function test_shelves_of_other_users_are_hidden(): void
    {
        $ownShelf = Shelf::factory()->for($this->user)->create();
        $otherShelf = Shelf::factory()->create();

        Livewire::test(ManageShelves::class)
            ->assertCanSeeTableRecords([$ownShelf])
            ->assertCanNotSeeTableRecords([$otherShelf]);
    }

    public function test_book_can_be_placed_on_shelves(): void
    {
        $shelves = Shelf::factory(2)->for($this->user)->create();

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Dune',
                'author_id' => Author::factory()->for($this->user)->create()->id,
                'shelves' => $shelves->modelKeys(),
                'genre' => 'sci_fi',
                'status' => 'want_to_read',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $book = Book::query()->where('title', 'Dune')->sole();
        $this->assertEqualsCanonicalizing($shelves->modelKeys(), $book->shelves->modelKeys());
    }

    public function test_book_cannot_be_placed_on_another_users_shelf(): void
    {
        $otherShelf = Shelf::factory()->create();

        Livewire::test(CreateBook::class)
            ->fillForm([
                'title' => 'Dune',
                'author_id' => Author::factory()->for($this->user)->create()->id,
                'shelves' => [$otherShelf->id],
                'genre' => 'sci_fi',
                'status' => 'want_to_read',
            ])
            ->call('create')
            ->assertHasFormErrors(['shelves.0']);

        $this->assertDatabaseMissing('book_shelf', ['shelf_id' => $otherShelf->id]);
    }

    public function test_books_table_can_be_filtered_by_shelf(): void
    {
        $shelf = Shelf::factory()->for($this->user)->create();
        $shelvedBook = Book::factory()->for($this->user)->hasAttached($shelf)->create();
        $otherBook = Book::factory()->for($this->user)->create();

        Livewire::test(ListBooks::class)
            ->filterTable('shelves', [$shelf->id])
            ->assertCanSeeTableRecords([$shelvedBook])
            ->assertCanNotSeeTableRecords([$otherBook]);
    }
}
