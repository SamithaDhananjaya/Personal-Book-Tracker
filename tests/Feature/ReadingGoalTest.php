<?php

namespace Tests\Feature;

use App\Filament\Resources\ReadingGoals\Pages\ManageReadingGoals;
use App\Filament\Resources\ReadingGoals\Widgets\ReadingGoalProgress;
use App\Models\Book;
use App\Models\ReadingGoal;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadingGoalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_goal_counts_only_books_finished_in_its_year(): void
    {
        $goal = ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 10]);

        Book::factory(2)->completed()->for($this->user)->create(['read_at' => '2026-03-01']);
        Book::factory()->completed()->for($this->user)->create(['read_at' => '2025-12-31']);
        Book::factory()->completed()->create(['read_at' => '2026-03-01']);

        $this->assertSame(2, $goal->books_finished);
        $this->assertSame(20, $goal->progress_percentage);
    }

    public function test_expected_pace_follows_the_calendar(): void
    {
        $this->travelTo('2026-07-02');

        $this->assertSame(12, ReadingGoal::factory()->make(['year' => 2026, 'target_books' => 24])->expected_by_today);
        $this->assertSame(24, ReadingGoal::factory()->make(['year' => 2025, 'target_books' => 24])->expected_by_today);
        $this->assertSame(0, ReadingGoal::factory()->make(['year' => 2027, 'target_books' => 24])->expected_by_today);
    }

    public function test_goal_can_be_created_for_the_signed_in_user(): void
    {
        Livewire::test(ManageReadingGoals::class)
            ->callAction(CreateAction::class, ['year' => 2026, 'target_books' => 24])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(ReadingGoal::class, [
            'user_id' => $this->user->id,
            'year' => 2026,
            'target_books' => 24,
        ]);
    }

    public function test_only_one_goal_per_year_is_allowed(): void
    {
        ReadingGoal::factory()->for($this->user)->create(['year' => 2026]);
        ReadingGoal::factory()->create(['year' => 2027]);

        Livewire::test(ManageReadingGoals::class)
            ->callAction(CreateAction::class, ['year' => 2026, 'target_books' => 24])
            ->assertHasFormErrors(['year' => 'unique']);

        Livewire::test(ManageReadingGoals::class)
            ->callAction(CreateAction::class, ['year' => 2027, 'target_books' => 24])
            ->assertHasNoFormErrors();
    }

    public function test_goals_of_other_users_are_hidden(): void
    {
        $ownGoal = ReadingGoal::factory()->for($this->user)->create();
        $otherGoal = ReadingGoal::factory()->create();

        Livewire::test(ManageReadingGoals::class)
            ->assertCanSeeTableRecords([$ownGoal])
            ->assertCanNotSeeTableRecords([$otherGoal]);
    }

    public function test_widget_prompts_for_a_goal_when_none_exists(): void
    {
        Livewire::test(ReadingGoalProgress::class)
            ->assertSee('No goal yet');
    }

    public function test_widget_shows_progress_towards_the_current_goal(): void
    {
        $this->travelTo('2026-02-01');

        ReadingGoal::factory()->for($this->user)->create(['year' => 2026, 'target_books' => 12]);
        Book::factory(3)->completed()->for($this->user)->create(['read_at' => '2026-01-15']);

        Livewire::test(ReadingGoalProgress::class)
            ->assertSee('3 / 12 books')
            ->assertSee('25% done · on track');
    }
}
