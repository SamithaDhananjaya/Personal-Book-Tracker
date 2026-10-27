<?php

namespace App\Filament\Resources\Books\RelationManagers;

use App\Enums\NoteType;
use App\Models\Note;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes & quotes';

    /**
     * Allow adding notes straight from the book's view page.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(NoteType::class)
                    ->default(NoteType::Note)
                    ->required(),
                TextInput::make('page')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(fn (): ?int => $this->getOwnerRecord()->total_pages),
                Textarea::make('content')
                    ->rows(4)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->defaultSort('page')
            ->columns([
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('content')
                    ->wrap()
                    ->formatStateUsing(fn (string $state, Note $record): string => $record->type === NoteType::Quote ? "“{$state}”" : $state)
                    ->searchable(),
                TextColumn::make('page')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(NoteType::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
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
