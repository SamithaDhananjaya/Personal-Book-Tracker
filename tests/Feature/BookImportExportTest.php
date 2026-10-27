<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Filament\Exports\BookExporter;
use App\Filament\Imports\BookImporter;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BookImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Column map matching the headers of a Goodreads library export.
     *
     * @var array<string, string>
     */
    private const GOODREADS_COLUMN_MAP = [
        'title' => 'Title',
        'author' => 'Author',
        'isbn' => 'ISBN13',
        'genre' => 'Bookshelves',
        'status' => 'Exclusive Shelf',
        'rating' => 'My Rating',
        'total_pages' => 'Number of Pages',
        'read_at' => 'Date Read',
        'shelves' => 'Bookshelves',
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_goodreads_row_is_imported(): void
    {
        $importer = BookImporter::test(self::GOODREADS_COLUMN_MAP, import: $this->import())
            ->import($this->goodreadsRow())
            ->assertImported();

        /** @var Book $book */
        $book = $importer->getRecord();

        $this->assertTrue($book->user->is($this->user));
        $this->assertSame('Dune', $book->title);
        $this->assertSame('9780441172719', $book->isbn);
        $this->assertSame('Frank Herbert', $book->author->name);
        $this->assertTrue($book->author->user->is($this->user));
        $this->assertSame(Genre::SciFi, $book->genre);
        $this->assertSame(BookStatus::Completed, $book->status);
        $this->assertSame(5, $book->rating);
        $this->assertSame(604, $book->current_page);
        $this->assertSame('2025-03-15', $book->read_at->toDateString());
        $this->assertEqualsCanonicalizing(['sci-fi', 'favorites'], $book->shelves->pluck('name')->all());
    }

    public function test_goodreads_unread_row_has_no_rating_or_read_date(): void
    {
        $importer = BookImporter::test(self::GOODREADS_COLUMN_MAP, import: $this->import())
            ->import($this->goodreadsRow([
                'My Rating' => '0',
                'Exclusive Shelf' => 'to-read',
                'Date Read' => '',
                'Bookshelves' => 'to-read',
            ]))
            ->assertImported();

        /** @var Book $book */
        $book = $importer->getRecord();

        $this->assertSame(BookStatus::WantToRead, $book->status);
        $this->assertNull($book->rating);
        $this->assertNull($book->read_at);
        $this->assertSame(0, $book->current_page);
        $this->assertCount(0, $book->shelves);
    }

    public function test_completed_row_without_read_date_is_not_dated_today(): void
    {
        $importer = BookImporter::test(self::GOODREADS_COLUMN_MAP, import: $this->import())
            ->import($this->goodreadsRow(['Date Read' => '']))
            ->assertImported();

        $this->assertNull($importer->getRecord()->read_at);
    }

    public function test_reimporting_updates_the_existing_book(): void
    {
        $author = Author::factory()->for($this->user)->create(['name' => 'Frank Herbert']);
        $existingBook = Book::factory()->for($this->user)->for($author)->create(['isbn' => '9780441172719']);

        $importer = BookImporter::test(self::GOODREADS_COLUMN_MAP, import: $this->import())
            ->import($this->goodreadsRow())
            ->assertImported();

        $this->assertTrue($importer->getRecord()->is($existingBook));
        $this->assertSame(1, Book::query()->count());
        $this->assertSame(1, Author::query()->count());
    }

    public function test_row_without_title_fails_validation(): void
    {
        $importer = BookImporter::test(self::GOODREADS_COLUMN_MAP, import: $this->import())
            ->import($this->goodreadsRow(['Title' => '']));

        $this->assertArrayHasKey('title', $importer->errors()->toArray());
    }

    public function test_genre_and_status_values_are_cast(): void
    {
        $this->assertSame(Genre::Tech, BookImporter::castGenre('tech'));
        $this->assertSame(Genre::NonFiction, BookImporter::castGenre('Non-Fiction'));
        $this->assertSame(Genre::Fiction, BookImporter::castGenre(null));
        $this->assertSame(BookStatus::Reading, BookImporter::castStatus('currently-reading'));
        $this->assertSame(BookStatus::Completed, BookImporter::castStatus('completed'));
        $this->assertSame(BookStatus::WantToRead, BookImporter::castStatus(''));
    }

    public function test_export_includes_only_the_signed_in_users_books(): void
    {
        Storage::fake('local');
        Bus::fake();

        Book::factory(3)->for($this->user)->create();
        Book::factory(2)->create();

        Livewire::test(ListBooks::class)
            ->callAction(TestAction::make('export')->table(), ['format' => ['csv']])
            ->assertHasNoFormErrors();

        $export = Export::query()->sole();

        $this->assertSame(BookExporter::class, $export->exporter);
        $this->assertTrue($export->user->is($this->user));
        $this->assertSame(3, $export->total_rows);
    }

    private function import(): Import
    {
        return new Import(['user_id' => $this->user->id]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function goodreadsRow(array $overrides = []): array
    {
        return [
            'Book Id' => '234225',
            'Title' => 'Dune',
            'Author' => 'Frank Herbert',
            'ISBN' => '="0441172717"',
            'ISBN13' => '="9780441172719"',
            'My Rating' => '5',
            'Number of Pages' => '604',
            'Date Read' => '2025/03/15',
            'Bookshelves' => 'sci-fi, favorites, read',
            'Exclusive Shelf' => 'read',
            ...$overrides,
        ];
    }
}
