<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Enums\BookStatus;
use App\Enums\Genre;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class BookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->columnSpanFull(),
                DatePicker::make('read_at'),
            ]);
    }
}
