<?php

namespace App\Filament\Resources\Books\Widgets;

use App\Enums\BookStatus;
use App\Models\Book;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Widgets\ChartWidget;

class BooksReadChart extends ChartWidget
{
    protected ?string $heading = 'Books finished per month';

    protected ?string $description = 'Last 12 months';

    protected static ?int $sort = 2;

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    protected function getData(): array
    {
        $firstMonth = CarbonImmutable::now()->startOfMonth()->subMonths(11);

        $finishedPerMonth = Book::query()
            ->whereBelongsTo(auth()->user())
            ->where('status', BookStatus::Completed)
            ->where('read_at', '>=', $firstMonth)
            ->pluck('read_at')
            ->countBy(fn (DateTimeInterface $readAt): string => $readAt->format('Y-m'));

        $months = collect(range(0, 11))->map(fn (int $offset): CarbonImmutable => $firstMonth->addMonths($offset));

        return [
            'datasets' => [
                [
                    'label' => 'Books finished',
                    'data' => $months->map(fn (CarbonImmutable $month): int => $finishedPerMonth->get($month->format('Y-m'), 0))->all(),
                ],
            ],
            'labels' => $months->map(fn (CarbonImmutable $month): string => $month->format('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
