<?php

namespace App\Filament\Exports;

use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Models\Book;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class BookExporter extends Exporter
{
    protected static ?string $model = Book::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title'),
            ExportColumn::make('author.name')
                ->label('Author'),
            ExportColumn::make('isbn')
                ->label('ISBN'),
            ExportColumn::make('genre')
                ->formatStateUsing(fn (Genre $state): string => $state->getLabel()),
            ExportColumn::make('status')
                ->formatStateUsing(fn (BookStatus $state): string => $state->getLabel()),
            ExportColumn::make('rating'),
            ExportColumn::make('total_pages'),
            ExportColumn::make('current_page'),
            ExportColumn::make('read_at')
                ->label('Read date')
                ->formatStateUsing(fn (Book $record): ?string => $record->read_at?->toDateString()),
            ExportColumn::make('shelves.name')
                ->label('Shelves'),
            ExportColumn::make('summary')
                ->enabledByDefault(false),
            ExportColumn::make('created_at')
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your book export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
