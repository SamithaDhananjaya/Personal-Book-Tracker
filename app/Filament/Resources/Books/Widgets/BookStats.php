<?php

namespace App\Filament\Resources\Books\Widgets;

use App\Models\Book;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total books', Book::count())
                ->description('Books in your collection')
                ->color('success'),
        ];
    }
}
