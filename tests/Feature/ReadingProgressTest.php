<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Book;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadingProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_progress_percentage_is_calculated_from_pages(): void
    {
        $book = Book::factory()->make(['total_pages' => 200, 'current_page' => 50]);

        $this->assertSame(25, $book->progress_percentage);
    }

    public function test_progress_percentage_is_null_without_page_count(): void
    {
        $book = Book::factory()->make(['total_pages' => null]);

        $this->assertNull($book->progress_percentage);
    }

    public function test_completing_a_book_sets_read_date_and_last_page(): void
    {
        $book = Book::factory()->reading()->for($this->user)->create(['total_pages' => 300]);

        $book->update(['status' => BookStatus::Completed]);

        $this->assertNotNull($book->read_at);
        $this->assertSame(300, $book->current_page);
    }

    public function test_logging_progress_starts_a_want_to_read_book(): void
    {
        $book = Book::factory()->for($this->user)->create(['total_pages' => 300]);

        Livewire::test(ListBooks::class)
            ->callAction(TestAction::make('logProgress')->table($book), ['current_page' => 40])
            ->assertHasNoFormErrors()
            ->assertNotified('Progress saved');

        $book->refresh();
        $this->assertSame(40, $book->current_page);
        $this->assertSame(BookStatus::Reading, $book->status);
    }

    public function test_logging_the_last_page_completes_the_book(): void
    {
        $book = Book::factory()->reading()->for($this->user)->create(['total_pages' => 300]);

        Livewire::test(ListBooks::class)
            ->callAction(TestAction::make('logProgress')->table($book), ['current_page' => 300])
            ->assertNotified('Book completed!');

        $book->refresh();
        $this->assertSame(BookStatus::Completed, $book->status);
        $this->assertNotNull($book->read_at);
    }

    public function test_logged_page_cannot_exceed_total_pages(): void
    {
        $book = Book::factory()->reading()->for($this->user)->create(['total_pages' => 300]);

        Livewire::test(ListBooks::class)
            ->callAction(TestAction::make('logProgress')->table($book), ['current_page' => 301])
            ->assertHasFormErrors(['current_page' => 'max']);
    }
}
