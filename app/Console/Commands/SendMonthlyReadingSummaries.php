<?php

namespace App\Console\Commands;

use App\Enums\BookStatus;
use App\Models\User;
use App\Notifications\MonthlyReadingSummary;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('books:send-monthly-summaries {--month= : Month to summarise as YYYY-MM, defaults to last month}')]
#[Description('Email readers a summary of the books they finished last month')]
class SendMonthlyReadingSummaries extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $month = $this->option('month')
            ? CarbonImmutable::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $sentCount = 0;

        User::query()
            ->where('wants_reading_emails', true)
            ->chunkById(100, function ($users) use ($month, &$sentCount): void {
                foreach ($users as $user) {
                    $finishedBooks = $user->books()
                        ->with('author')
                        ->where('status', BookStatus::Completed)
                        ->whereBetween('read_at', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                        ->orderBy('read_at')
                        ->get();

                    if ($finishedBooks->isEmpty()) {
                        continue;
                    }

                    $user->notify(new MonthlyReadingSummary(
                        month: $month,
                        finishedBooks: $finishedBooks,
                        pagesRead: (int) $finishedBooks->sum('total_pages'),
                        goal: $user->readingGoals()->where('year', $month->year)->first(),
                    ));

                    $sentCount++;
                }
            });

        $this->components->info("Sent {$month->format('F Y')} summaries to {$sentCount} ".str('reader')->plural($sentCount).'.');

        return self::SUCCESS;
    }
}
