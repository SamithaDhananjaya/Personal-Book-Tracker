<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\Book;
use App\Models\ReadingGoal;
use App\Models\User;
use App\Notifications\MonthlyReadingSummary;
use App\Notifications\StalledBooksReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReadingEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_readers_are_reminded_about_stalled_books_only(): void
    {
        $user = User::factory()->create();
        $stalledBook = Book::factory()->reading()->for($user)->create(['updated_at' => now()->subDays(20)]);
        Book::factory()->reading()->for($user)->create(['updated_at' => now()->subDays(3)]);
        Book::factory()->for($user)->create(['updated_at' => now()->subDays(30)]);

        $this->artisan('books:send-stalled-reminders')
            ->expectsOutputToContain('Sent stalled book reminders to 1 reader.')
            ->assertSuccessful();

        Notification::assertSentTo(
            $user,
            StalledBooksReminder::class,
            fn (StalledBooksReminder $notification): bool => $notification->stalledBooks->modelKeys() === [$stalledBook->id],
        );
    }

    public function test_readers_without_stalled_books_or_who_opted_out_are_not_emailed(): void
    {
        $activeReader = User::factory()->create();
        Book::factory()->reading()->for($activeReader)->create(['updated_at' => now()->subDay()]);

        $optedOutReader = User::factory()->create(['wants_reading_emails' => false]);
        Book::factory()->reading()->for($optedOutReader)->create(['updated_at' => now()->subDays(20)]);

        $this->artisan('books:send-stalled-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_stalled_reminder_mail_lists_the_books(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->reading()->for($user)->create(['total_pages' => 200, 'current_page' => 50]);

        $mail = (new StalledBooksReminder($user->books()->with('author')->get()))->toMail($user);

        $this->assertSame('Pick up where you left off', $mail->subject);
        $this->assertContains("• {$book->title} by {$book->author->name} (25% read)", $mail->introLines);
    }

    public function test_monthly_summary_covers_books_finished_last_month(): void
    {
        $this->travelTo('2026-11-01 08:00');

        $user = User::factory()->create();
        ReadingGoal::factory()->for($user)->create(['year' => 2026, 'target_books' => 12]);
        $octoberBooks = Book::factory(2)->completed()->for($user)->create(['read_at' => '2026-10-10', 'total_pages' => 300]);
        Book::factory()->completed()->for($user)->create(['read_at' => '2026-09-30']);

        $this->artisan('books:send-monthly-summaries')
            ->expectsOutputToContain('Sent October 2026 summaries to 1 reader.')
            ->assertSuccessful();

        Notification::assertSentTo($user, MonthlyReadingSummary::class, function (MonthlyReadingSummary $notification) use ($octoberBooks, $user): bool {
            $mail = $notification->toMail($user);

            return $notification->finishedBooks->modelKeys() === $octoberBooks->modelKeys()
                && $notification->pagesRead === 600
                && $mail->subject === 'Your reading in October 2026'
                && in_array('2026 goal: 3 of 12 books (25%).', $mail->introLines, true);
        });
    }

    public function test_monthly_summary_skips_readers_with_nothing_finished(): void
    {
        $user = User::factory()->create();
        Book::factory()->reading()->for($user)->create();

        $this->artisan('books:send-monthly-summaries', ['--month' => '2026-10'])
            ->expectsOutputToContain('Sent October 2026 summaries to 0 readers.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_email_commands_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => $event->command);

        $this->assertTrue($commands->contains(fn (string $command): bool => str_contains($command, 'books:send-stalled-reminders')));
        $this->assertTrue($commands->contains(fn (string $command): bool => str_contains($command, 'books:send-monthly-summaries')));
    }

    public function test_reader_can_opt_out_from_their_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['wants_reading_emails' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->refresh()->wants_reading_emails);
    }
}
