<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Filament\Resources\Shelves\ShelfResource;
use App\Models\Shelf;
use App\Services\OpenLibrary;
use App\Services\ReadingAssistant;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('isbn')
                    ->label('ISBN')
                    ->helperText('Enter an ISBN and use the search button to fill in the details from Open Library.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('isbn', filled($state) ? OpenLibrary::normalizeIsbn($state) : null))
                    ->regex('/^(\d{9}[\dX]|\d{13})$/')
                    ->validationMessages(['regex' => 'Enter a valid 10 or 13 digit ISBN.'])
                    ->suffixAction(
                        Action::make('lookupIsbn')
                            ->label('Look up')
                            ->icon(Heroicon::OutlinedMagnifyingGlass)
                            ->action(fn (Get $get, Set $set) => static::fillFromOpenLibrary($get, $set)),
                    ),
                TextInput::make('title')
                    ->required(),
                Select::make('author_id')
                    ->relationship(
                        'author',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereBelongsTo(auth()->user()),
                    )
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data): int => auth()->user()->authors()->create($data)->getKey())
                    ->required(),
                Select::make('shelves')
                    ->relationship(
                        'shelves',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereBelongsTo(auth()->user()),
                    )
                    ->multiple()
                    ->preload()
                    ->nestedRecursiveRules([
                        Rule::exists(Shelf::class, 'id')->where('user_id', auth()->id()),
                    ])
                    ->createOptionForm(ShelfResource::formComponents())
                    ->createOptionUsing(fn (array $data): int => auth()->user()->shelves()->create($data)->getKey()),
                FileUpload::make('cover_path')
                    ->label('Book cover')
                    ->image()
                    ->disk('public')
                    ->directory('book-covers')
                    ->visibility('public')
                    ->maxSize(2048),
                Select::make('genre')
                    ->options(Genre::class)
                    ->required(),
                Select::make('status')
                    ->options(BookStatus::class)
                    ->default('want_to_read')
                    ->required(),
                TextInput::make('rating')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(5),
                TextInput::make('total_pages')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->live(onBlur: true),
                TextInput::make('current_page')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(fn (Get $get): ?int => filled($get('total_pages')) ? (int) $get('total_pages') : null)
                    ->default(0),
                Textarea::make('summary')
                    ->rows(4)
                    ->hintAction(
                        Action::make('generateSummary')
                            ->label('Write with AI')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->visible(fn (): bool => app(ReadingAssistant::class)->isConfigured())
                            ->action(fn (Get $get, Set $set) => static::generateSummary($get, $set)),
                    )
                    ->columnSpanFull(),
                DatePicker::make('read_at'),
            ]);
    }

    /**
     * Ask Claude for a spoiler-free summary of the book in the form.
     */
    public static function generateSummary(Get $get, Set $set): void
    {
        if (blank($get('title'))) {
            Notification::make()->warning()->title('Enter a title first.')->send();

            return;
        }

        $authorName = filled($get('author_id'))
            ? auth()->user()->authors()->whereKey($get('author_id'))->value('name')
            : null;

        $summary = app(ReadingAssistant::class)->summarize($get('title'), $authorName);

        if ($summary === null) {
            Notification::make()->warning()->title('Could not write a summary for this book.')->send();

            return;
        }

        $set('summary', $summary);

        Notification::make()->success()->title('Summary written. Review it before saving.')->send();
    }

    /**
     * Fill empty book fields from Open Library using the entered ISBN.
     */
    public static function fillFromOpenLibrary(Get $get, Set $set): void
    {
        $isbn = OpenLibrary::normalizeIsbn((string) $get('isbn'));

        if (! preg_match('/^(\d{9}[\dX]|\d{13})$/', $isbn)) {
            Notification::make()->warning()->title('Enter a valid 10 or 13 digit ISBN first.')->send();

            return;
        }

        $openLibrary = app(OpenLibrary::class);

        try {
            $details = $openLibrary->findByIsbn($isbn);
        } catch (ConnectionException|RequestException) {
            Notification::make()->danger()->title('Open Library could not be reached. Try again later.')->send();

            return;
        }

        if ($details === null) {
            Notification::make()->warning()->title("No book found for ISBN {$isbn}.")->send();

            return;
        }

        $set('isbn', $isbn);
        $set('title', $details['title']);

        if ($details['authors'] !== []) {
            $set('author_id', auth()->user()->authors()->firstOrCreate(['name' => $details['authors'][0]])->getKey());
        }

        if ($details['total_pages'] !== null) {
            $set('total_pages', $details['total_pages']);
        }

        if ($details['genre'] !== null && blank($get('genre'))) {
            $set('genre', $details['genre']->value);
        }

        if ($details['cover_url'] !== null && blank($get('cover_path'))) {
            $coverPath = $openLibrary->downloadCover($details['cover_url'], $isbn);

            if ($coverPath !== null) {
                $set('cover_path', [(string) Str::uuid() => $coverPath]);
            }
        }

        Notification::make()->success()->title("Filled in details for “{$details['title']}”.")->send();
    }
}
