<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Models\Book;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('author.name'),
                TextEntry::make('genre')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('rating')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->helperText(fn (Book $record): ?string => $record->total_pages
                        ? "{$record->current_page} of {$record->total_pages} pages"
                        : null)
                    ->placeholder('-'),
                TextEntry::make('summary')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('read_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
