<?php

namespace Tests\Feature;

use App\Enums\Genre;
use App\Filament\Resources\Books\Widgets\BooksReadChart;
use App\Filament\Resources\Books\Widgets\BookStats;
use App\Filament\Resources\Books\Widgets\GenreBreakdownChart;
use App\Models\Book;
use App\Models\User;
use Filament\Pages\Dashboard;
use Filament\Widgets\ChartWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_dashboard_renders_with_widgets(): void
    {
        Book::factory()->completed()->for($this->user)->create();

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSeeLivewire(BookStats::class)
            ->assertSeeLivewire(BooksReadChart::class)
            ->assertSeeLivewire(GenreBreakdownChart::class);
    }

    public function test_stats_only_count_the_signed_in_users_books(): void
    {
        Book::factory(2)->reading()->for($this->user)->create();
        Book::factory()->completed()->for($this->user)->create(['read_at' => now(), 'rating' => 4]);
        Book::factory(5)->reading()->create();

        Livewire::test(BookStats::class)
            ->assertSeeInOrder(['Total books', '3'])
            ->assertSeeInOrder(['Reading now', '2'])
            ->assertSeeInOrder(['Finished in '.now()->year, '1'])
            ->assertSee('4.0 / 5');
    }

    public function test_books_read_chart_groups_finished_books_by_month(): void
    {
        Book::factory(2)->completed()->for($this->user)->create(['read_at' => now()]);
        Book::factory()->completed()->for($this->user)->create(['read_at' => now()->subMonthNoOverflow()]);
        Book::factory()->completed()->for($this->user)->create(['read_at' => now()->subYears(2)]);
        Book::factory()->completed()->create(['read_at' => now()]);

        $data = $this->chartData(new BooksReadChart);

        $this->assertCount(12, $data['labels']);
        $this->assertSame(now()->format('M Y'), end($data['labels']));
        $this->assertSame(2, $data['datasets'][0]['data'][11]);
        $this->assertSame(1, $data['datasets'][0]['data'][10]);
        $this->assertSame(3, array_sum($data['datasets'][0]['data']));
    }

    public function test_genre_chart_counts_books_per_genre(): void
    {
        Book::factory(3)->for($this->user)->create(['genre' => Genre::SciFi]);
        Book::factory()->for($this->user)->create(['genre' => Genre::Tech]);
        Book::factory()->create(['genre' => Genre::SciFi]);

        $data = $this->chartData(new GenreBreakdownChart);
        $counts = array_combine($data['labels'], $data['datasets'][0]['data']);

        $this->assertSame(3, $counts['Sci-Fi']);
        $this->assertSame(1, $counts['Tech']);
        $this->assertSame(0, $counts['Fiction']);
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    private function chartData(ChartWidget $widget): array
    {
        return (fn (): array => $this->getData())->call($widget);
    }
}
