<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Enums\BookStatus;
use App\Enums\Genre;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Select::make('author_id')
                    ->relationship('author', 'name')
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
                    ->numeric(),
                Textarea::make('summary')
                    ->columnSpanFull(),
                DatePicker::make('read_at'),
            ]);
    }
}
