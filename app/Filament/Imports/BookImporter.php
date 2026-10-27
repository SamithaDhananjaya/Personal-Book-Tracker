<?php

namespace App\Filament\Imports;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Models\Author;
use App\Models\Book;
use App\Models\Shelf;
use App\Services\OpenLibrary;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Imports books from a CSV, including Goodreads library exports.
 */
class BookImporter extends Importer
{
    protected static ?string $model = Book::class;

    /**
     * Goodreads shelves that only describe reading status, not a real shelf.
     *
     * @var list<string>
     */
    private const GOODREADS_STATUS_SHELVES = ['read', 'currently-reading', 'to-read'];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('title')
                ->requiredMapping()
                ->guess(['Title', 'Book title'])
                ->rules(['required', 'max:255'])
                ->example('Dune'),
            ImportColumn::make('author')
                ->requiredMapping()
                ->guess(['Author', 'Author name'])
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(function (Book $record, string $state, Importer $importer): void {
                    $record->author_id = Author::query()->firstOrCreate([
                        'user_id' => $importer->getImport()->user_id,
                        'name' => trim($state),
                    ])->getKey();
                })
                ->example('Frank Herbert'),
            ImportColumn::make('isbn')
                ->label('ISBN')
                ->guess(['ISBN13', 'ISBN 13', 'ISBN'])
                ->castStateUsing(fn (?string $state): ?string => filled($normalized = OpenLibrary::normalizeIsbn((string) $state)) ? $normalized : null)
                ->rules(['nullable', 'regex:/^(\d{9}[\dX]|\d{13})$/'])
                ->example('9780441172719'),
            ImportColumn::make('genre')
                ->guess(['Genre', 'Bookshelves'])
                ->castStateUsing(fn (?string $state): string => static::castGenre($state)->value)
                ->rules([Rule::enum(Genre::class)])
                ->example('sci_fi'),
            ImportColumn::make('status')
                ->guess(['Status', 'Exclusive Shelf'])
                ->castStateUsing(fn (?string $state): string => static::castStatus($state)->value)
                ->rules([Rule::enum(BookStatus::class)])
                ->example('completed'),
            ImportColumn::make('rating')
                ->guess(['Rating', 'My Rating'])
                ->integer()
                ->castStateUsing(fn (?int $state): ?int => $state ?: null)
                ->rules(['nullable', 'integer', 'between:1,5'])
                ->example('5'),
            ImportColumn::make('total_pages')
                ->guess(['Total pages', 'Number of Pages', 'Pages'])
                ->integer()
                ->castStateUsing(fn (?int $state): ?int => $state ?: null)
                ->rules(['nullable', 'integer', 'min:1', 'max:65535'])
                ->example('604'),
            ImportColumn::make('read_at')
                ->label('Read date')
                ->guess(['Read at', 'Date Read', 'Read date'])
                ->castStateUsing(fn (?string $state): ?string => filled($state) ? Carbon::parse(trim($state))->toDateString() : null)
                ->rules(['nullable', 'date'])
                ->example('2026-03-15'),
            ImportColumn::make('summary')
                ->guess(['Summary', 'Description'])
                ->rules(['nullable', 'max:65535']),
            ImportColumn::make('shelves')
                ->guess(['Shelves', 'Bookshelves'])
                ->array(',')
                ->fillRecordUsing(fn (): null => null)
                ->example('favorites, book-club'),
        ];
    }

    /**
     * Update an existing book with the same ISBN (or title) instead of duplicating it.
     */
    public function resolveRecord(): Book
    {
        $books = Book::query()->where('user_id', $this->import->user_id);

        $existingBook = filled($this->data['isbn'] ?? null)
            ? (clone $books)->where('isbn', $this->data['isbn'])->first()
            : null;

        $existingBook ??= (clone $books)->where('title', $this->data['title'])->first();

        return $existingBook ?? new Book([
            'user_id' => $this->import->user_id,
            'genre' => Genre::Fiction,
            'status' => BookStatus::WantToRead,
        ]);
    }

    /**
     * Save without the model's auto-filled read date, so books without a
     * "Date Read" are not all counted as finished today.
     */
    public function saveRecord(): void
    {
        if ($this->record->status === BookStatus::Completed && $this->record->total_pages !== null) {
            $this->record->current_page = $this->record->total_pages;
        }

        $this->record->current_page ??= 0;

        Book::withoutEvents(fn (): bool => $this->record->save());
    }

    protected function afterSave(): void
    {
        $shelfNames = collect($this->data['shelves'] ?? [])
            ->map(fn (string $name): string => trim($name))
            ->reject(fn (string $name): bool => in_array(Str::lower($name), self::GOODREADS_STATUS_SHELVES, true))
            ->filter()
            ->unique();

        if ($shelfNames->isEmpty()) {
            return;
        }

        $shelfIds = $shelfNames->map(fn (string $name): int => Shelf::query()->firstOrCreate(
            ['user_id' => $this->import->user_id, 'name' => $name],
        )->getKey());

        $this->record->shelves()->syncWithoutDetaching($shelfIds->all());
    }

    public static function castGenre(?string $state): Genre
    {
        $state = trim((string) $state);

        return Genre::tryFrom($state)
            ?? collect(Genre::cases())->first(fn (Genre $genre): bool => Str::lower($genre->getLabel()) === Str::lower($state))
            ?? (filled($state) ? app(OpenLibrary::class)->guessGenre([$state]) : null)
            ?? Genre::Fiction;
    }

    public static function castStatus(?string $state): BookStatus
    {
        $state = Str::lower(trim((string) $state));

        return BookStatus::tryFrom($state) ?? match ($state) {
            'read', 'completed', 'finished' => BookStatus::Completed,
            'currently-reading', 'reading' => BookStatus::Reading,
            default => BookStatus::WantToRead,
        };
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your book import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
