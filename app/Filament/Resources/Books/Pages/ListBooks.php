<?php

namespace App\Filament\Resources\Books\Pages;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Filament\Resources\Books\BookResource;
use App\Services\ReadingAssistant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBooks extends ListRecords
{
    protected static string $resource = BookResource::class;

    /**
     * Suggestions from the last "Suggest next reads" request, kept between opening and submitting the modal.
     *
     * @var list<array{title: string, author: string, reason: string, genre: string}>
     */
    public array $nextReadSuggestions = [];

    protected function getHeaderActions(): array
    {
        return [
            $this->suggestNextReadsAction(),
            CreateAction::make(),
        ];
    }

    protected function suggestNextReadsAction(): Action
    {
        return Action::make('suggestNextReads')
            ->label('Suggest next reads')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => app(ReadingAssistant::class)->isConfigured())
            ->modalHeading('Suggested next reads')
            ->modalDescription('Based on the books you rated 4 stars or more. Pick any to add to your want-to-read list.')
            ->modalSubmitActionLabel('Add to want to read')
            ->fillForm(function (Action $action): array {
                $this->nextReadSuggestions = app(ReadingAssistant::class)->suggestNextReads(auth()->user());

                if ($this->nextReadSuggestions === []) {
                    Notification::make()
                        ->warning()
                        ->title('No suggestions yet')
                        ->body('Rate a few finished books 4 stars or more, then try again.')
                        ->send();

                    $action->cancel();
                }

                return ['selected' => []];
            })
            ->schema([
                CheckboxList::make('selected')
                    ->hiddenLabel()
                    ->options(fn (): array => collect($this->nextReadSuggestions)
                        ->map(fn (array $suggestion): string => "{$suggestion['title']} — {$suggestion['author']}")
                        ->all())
                    ->descriptions(fn (): array => collect($this->nextReadSuggestions)->pluck('reason')->all()),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();

                $added = collect($data['selected'] ?? [])
                    ->map(fn (int|string $index): ?array => $this->nextReadSuggestions[(int) $index] ?? null)
                    ->filter()
                    ->each(fn (array $suggestion) => $user->books()->create([
                        'title' => $suggestion['title'],
                        'author_id' => $user->authors()->firstOrCreate(['name' => $suggestion['author']])->getKey(),
                        'genre' => Genre::tryFrom($suggestion['genre']) ?? Genre::Fiction,
                        'status' => BookStatus::WantToRead,
                    ]));

                $this->nextReadSuggestions = [];

                Notification::make()
                    ->success()
                    ->title("Added {$added->count()} ".str('book')->plural($added->count()).' to your want-to-read list')
                    ->send();
            });
    }
}
