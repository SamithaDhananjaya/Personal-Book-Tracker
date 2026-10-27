<?php

namespace App\Filament\Resources\Shelves;

use App\Filament\Resources\Shelves\Pages\ManageShelves;
use App\Models\Shelf;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ShelfResource extends Resource
{
    protected static ?string $model = Shelf::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::formComponents());
    }

    /**
     * Shelf fields, shared with the inline "create shelf" option on the book form.
     *
     * @return array<int, TextInput|Select>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')
                ->unique(
                    table: Shelf::class,
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('user_id', auth()->id()),
                )
                ->validationMessages(['unique' => 'You already have a shelf with this name.'])
                ->maxLength(255)
                ->required(),
            Select::make('color')
                ->options(Shelf::COLORS)
                ->default('gray')
                ->required(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->badge()
                    ->color(fn (Shelf $record): string => $record->color)
                    ->searchable(),
                TextColumn::make('books_count')
                    ->label('Books')
                    ->counts('books')
                    ->sortable(),
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
            'index' => ManageShelves::route('/'),
        ];
    }
}
