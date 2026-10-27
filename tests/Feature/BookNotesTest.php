<?php

namespace Tests\Feature;

use App\Enums\NoteType;
use App\Filament\Resources\Books\Pages\ViewBook;
use App\Filament\Resources\Books\RelationManagers\NotesRelationManager;
use App\Models\Book;
use App\Models\Note;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookNotesTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->book = Book::factory()->for($user)->create(['total_pages' => 300]);
    }

    public function test_notes_of_the_book_are_listed(): void
    {
        $notes = Note::factory(2)->for($this->book)->create();
        $otherNote = Note::factory()->create();

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $this->book,
            'pageClass' => ViewBook::class,
        ])
            ->assertCanSeeTableRecords($notes)
            ->assertCanNotSeeTableRecords([$otherNote]);
    }

    public function test_quote_can_be_added_from_the_view_page(): void
    {
        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $this->book,
            'pageClass' => ViewBook::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'type' => NoteType::Quote->value,
                'page' => 42,
                'content' => 'Fear is the mind-killer.',
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Note::class, [
            'book_id' => $this->book->id,
            'type' => NoteType::Quote->value,
            'page' => 42,
        ]);
    }

    public function test_note_page_cannot_exceed_the_books_page_count(): void
    {
        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $this->book,
            'pageClass' => ViewBook::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'type' => NoteType::Note->value,
                'page' => 301,
                'content' => 'Too far',
            ])
            ->assertHasFormErrors(['page' => 'max']);
    }
}
