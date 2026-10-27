<?php

namespace App\Filament\Resources\Books\Tables;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Filament\Exports\BookExporter;
use App\Filament\Imports\BookImporter;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('genre')
                    ->badge()
                    ->searchable(),
                TextColumn::make('shelves.name')
                    ->label('Shelves')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->description(fn (Book $record): ?string => $record->total_pages
                        ? "{$record->current_page} / {$record->total_pages} pages"
                        : null)
                    ->placeholder('-'),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('read_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BookStatus::class),
                SelectFilter::make('genre')
                    ->options(Genre::class),
                SelectFilter::make('author')
                    ->relationship(
                        'author',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereBelongsTo(auth()->user()),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('shelves')
                    ->relationship(
                        'shelves',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereBelongsTo(auth()->user()),
                    )
                    ->multiple()
                    ->preload(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(BookImporter::class)
                    ->label('Import CSV')
                    ->modalDescription('Upload a CSV of books, such as a Goodreads library export.'),
                ExportAction::make()
                    ->exporter(BookExporter::class)
                    ->label('Export'),
            ])
            ->recordActions([
                Action::make('logProgress')
                    ->label('Log progress')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->visible(fn (Book $record): bool => $record->status !== BookStatus::Completed)
                    ->fillForm(fn (Book $record): array => ['current_page' => $record->current_page])
                    ->schema(fn (Book $record): array => [
                        TextInput::make('current_page')
                            ->label('Current page')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue($record->total_pages)
                            ->helperText($record->total_pages ? "of {$record->total_pages} pages" : null)
                            ->required(),
                    ])
                    ->action(function (array $data, Book $record): void {
                        $record->logProgress((int) $data['current_page']);

                        Notification::make()
                            ->success()
                            ->title($record->status === BookStatus::Completed ? 'Book completed!' : 'Progress saved')
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
