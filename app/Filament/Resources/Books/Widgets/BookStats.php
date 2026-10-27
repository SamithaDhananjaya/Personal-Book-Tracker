<?php

namespace App\Filament\Resources\Books\Widgets;

use App\Enums\BookStatus;
use App\Models\Book;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $books = Book::query()->whereBelongsTo(auth()->user());

        $finishedThisYear = (clone $books)
            ->where('status', BookStatus::Completed)
            ->whereYear('read_at', now()->year)
            ->count();

        $averageRating = (clone $books)->whereNotNull('rating')->avg('rating');

        return [
            Stat::make('Total books', (clone $books)->count())
                ->description('Books in your collection')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('success'),
            Stat::make('Reading now', (clone $books)->where('status', BookStatus::Reading)->count())
                ->description((clone $books)->where('status', BookStatus::WantToRead)->count().' on your want-to-read list')
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('warning'),
            Stat::make('Finished in '.now()->year, $finishedThisYear)
                ->description('Completed this year')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            Stat::make('Average rating', $averageRating === null ? '-' : number_format($averageRating, 1).' / 5')
                ->description('Across rated books')
                ->icon(Heroicon::OutlinedStar)
                ->color('info'),
        ];
    }
}
