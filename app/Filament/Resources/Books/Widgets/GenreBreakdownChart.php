<?php

namespace App\Filament\Resources\Books\Widgets;

use App\Enums\Genre;
use App\Models\Book;
use Filament\Widgets\ChartWidget;

class GenreBreakdownChart extends ChartWidget
{
    protected ?string $heading = 'Books by genre';

    protected static ?int $sort = 3;

    /**
     * Chart.js colours matching each genre's Filament badge colour.
     *
     * @var array<string, string>
     */
    private const GENRE_COLORS = [
        'info' => '#3b82f6',
        'warning' => '#f59e0b',
        'success' => '#22c55e',
        'danger' => '#ef4444',
    ];

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    protected function getData(): array
    {
        $booksPerGenre = Book::query()
            ->whereBelongsTo(auth()->user())
            ->toBase()
            ->selectRaw('genre, count(*) as aggregate')
            ->groupBy('genre')
            ->pluck('aggregate', 'genre');

        $genres = collect(Genre::cases());

        return [
            'datasets' => [
                [
                    'label' => 'Books',
                    'data' => $genres->map(fn (Genre $genre): int => (int) $booksPerGenre->get($genre->value, 0))->all(),
                    'backgroundColor' => $genres->map(fn (Genre $genre): string => self::GENRE_COLORS[$genre->getColor()])->all(),
                ],
            ],
            'labels' => $genres->map(fn (Genre $genre): string => $genre->getLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
