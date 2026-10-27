<?php

namespace Tests\Feature;

use App\Enums\Genre;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use App\Services\OpenLibrary;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IsbnLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        Storage::fake('public');
    }

    public function test_isbn_is_normalized(): void
    {
        $this->assertSame('9780441172719', OpenLibrary::normalizeIsbn('978-0-441-17271-9'));
        $this->assertSame('044117271X', OpenLibrary::normalizeIsbn('0 441 17271 x'));
    }

    public function test_genre_is_guessed_from_subjects(): void
    {
        $openLibrary = new OpenLibrary;

        $this->assertSame(Genre::SciFi, $openLibrary->guessGenre(['Fiction', 'Science fiction']));
        $this->assertSame(Genre::Tech, $openLibrary->guessGenre(['Computer programming']));
        $this->assertSame(Genre::NonFiction, $openLibrary->guessGenre(['Nonfiction', 'History']));
        $this->assertSame(Genre::Fiction, $openLibrary->guessGenre(['Fiction, general']));
        $this->assertSame(Genre::NonFiction, $openLibrary->guessGenre(['Biography']));
        $this->assertNull($openLibrary->guessGenre([]));
    }

    public function test_book_details_are_found_by_isbn(): void
    {
        $this->fakeOpenLibrary();

        $details = (new OpenLibrary)->findByIsbn('978-0-441-17271-9');

        $this->assertSame('Dune', $details['title']);
        $this->assertSame(['Frank Herbert'], $details['authors']);
        $this->assertSame(604, $details['total_pages']);
        $this->assertSame(Genre::SciFi, $details['genre']);
        $this->assertSame('https://covers.openlibrary.org/b/id/1-L.jpg', $details['cover_url']);
    }

    public function test_unknown_isbn_returns_null(): void
    {
        Http::fake(['openlibrary.org/api/books*' => Http::response([])]);

        $this->assertNull((new OpenLibrary)->findByIsbn('9780000000000'));
    }

    public function test_lookup_action_fills_the_book_form(): void
    {
        $this->fakeOpenLibrary();

        Livewire::test(CreateBook::class)
            ->fillForm(['isbn' => '978-0-441-17271-9'])
            ->callAction(TestAction::make('lookupIsbn')->schemaComponent('isbn'))
            ->assertNotified('Filled in details for “Dune”.')
            ->assertSchemaStateSet([
                'isbn' => '9780441172719',
                'title' => 'Dune',
                'total_pages' => 604,
                'genre' => Genre::SciFi,
            ])
            ->fillForm(['status' => 'want_to_read'])
            ->call('create')
            ->assertHasNoFormErrors();

        $author = Author::query()->where('name', 'Frank Herbert')->sole();
        $this->assertTrue($author->user->is($this->user));

        $book = Book::query()->where('isbn', '9780441172719')->sole();
        $this->assertTrue($book->author->is($author));
        $this->assertNotNull($book->cover_path);
        Storage::disk('public')->assertExists($book->cover_path);
    }

    public function test_lookup_reuses_an_existing_author(): void
    {
        $this->fakeOpenLibrary();
        $author = Author::factory()->for($this->user)->create(['name' => 'Frank Herbert']);

        Livewire::test(CreateBook::class)
            ->fillForm(['isbn' => '9780441172719'])
            ->callAction(TestAction::make('lookupIsbn')->schemaComponent('isbn'))
            ->assertSchemaStateSet(['author_id' => $author->id]);

        $this->assertSame(1, Author::query()->where('name', 'Frank Herbert')->count());
    }

    public function test_lookup_warns_when_no_book_is_found(): void
    {
        Http::fake(['openlibrary.org/api/books*' => Http::response([])]);

        Livewire::test(CreateBook::class)
            ->fillForm(['isbn' => '9780000000000'])
            ->callAction(TestAction::make('lookupIsbn')->schemaComponent('isbn'))
            ->assertNotified('No book found for ISBN 9780000000000.')
            ->assertSchemaStateSet(['title' => null]);
    }

    public function test_lookup_reports_when_open_library_is_down(): void
    {
        Http::fake(['openlibrary.org/api/books*' => Http::response(status: 503)]);

        Livewire::test(CreateBook::class)
            ->fillForm(['isbn' => '9780441172719'])
            ->callAction(TestAction::make('lookupIsbn')->schemaComponent('isbn'))
            ->assertNotified('Open Library could not be reached. Try again later.');
    }

    public function test_invalid_isbn_is_rejected_on_save(): void
    {
        Livewire::test(CreateBook::class)
            ->fillForm([
                'isbn' => '12345',
                'title' => 'Dune',
                'author_id' => Author::factory()->for($this->user)->create()->id,
                'genre' => 'sci_fi',
                'status' => 'want_to_read',
            ])
            ->call('create')
            ->assertHasFormErrors(['isbn' => 'regex']);
    }

    private function fakeOpenLibrary(): void
    {
        Http::fake([
            'openlibrary.org/api/books*' => Http::response([
                'ISBN:9780441172719' => [
                    'title' => 'Dune',
                    'authors' => [['name' => 'Frank Herbert', 'url' => 'https://openlibrary.org/authors/OL79034A']],
                    'number_of_pages' => 604,
                    'subjects' => [['name' => 'Fiction'], ['name' => 'Science fiction']],
                    'cover' => [
                        'medium' => 'https://covers.openlibrary.org/b/id/1-M.jpg',
                        'large' => 'https://covers.openlibrary.org/b/id/1-L.jpg',
                    ],
                ],
            ]),
            'covers.openlibrary.org/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }
}
