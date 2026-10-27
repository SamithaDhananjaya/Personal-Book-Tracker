<?php

namespace App\Filament\Resources\ReadingGoals;

use App\Filament\Resources\ReadingGoals\Pages\ManageReadingGoals;
use App\Models\ReadingGoal;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ReadingGoalResource extends Resource
{
    protected static ?string $model = ReadingGoal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $recordTitleAttribute = 'year';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->numeric()
                    ->integer()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->default(now()->year)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('user_id', auth()->id()),
                    )
                    ->validationMessages(['unique' => 'You already have a goal for this year.'])
                    ->required(),
                TextInput::make('target_books')
                    ->label('Books to read')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('year')
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('target_books')
                    ->label('Target'),
                TextColumn::make('books_finished')
                    ->label('Finished'),
                TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->badge()
                    ->color(fn (ReadingGoal $record): string => $record->books_finished >= $record->expected_by_today ? 'success' : 'warning'),
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

    /**
     * Limit records to those owned by the signed-in user.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereBelongsTo(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReadingGoals::route('/'),
        ];
    }
}
