<?php

namespace App\Filament\Resources\ReadingGoals\Widgets;

use App\Filament\Resources\ReadingGoals\ReadingGoalResource;
use App\Models\ReadingGoal;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReadingGoalProgress extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $year = now()->year;

        $goal = ReadingGoal::query()
            ->whereBelongsTo(auth()->user())
            ->where('year', $year)
            ->first();

        if ($goal === null) {
            return [
                Stat::make("{$year} reading goal", 'No goal yet')
                    ->description('Set a yearly target to track your pace')
                    ->descriptionIcon(Heroicon::OutlinedPlusCircle)
                    ->icon(Heroicon::OutlinedTrophy)
                    ->url(ReadingGoalResource::getUrl())
                    ->color('gray'),
            ];
        }

        $booksAhead = $goal->books_finished - $goal->expected_by_today;

        return [
            Stat::make("{$year} reading goal", "{$goal->books_finished} / {$goal->target_books} books")
                ->description(match (true) {
                    $goal->books_finished >= $goal->target_books => 'Goal reached!',
                    $booksAhead >= 0 => "{$goal->progress_percentage}% done · on track",
                    default => "{$goal->progress_percentage}% done · ".abs($booksAhead).' behind pace',
                })
                ->descriptionIcon($booksAhead >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->icon(Heroicon::OutlinedTrophy)
                ->url(ReadingGoalResource::getUrl())
                ->color($booksAhead >= 0 ? 'success' : 'warning'),
        ];
    }
}
