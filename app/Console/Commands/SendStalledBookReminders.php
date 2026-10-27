<?php

namespace App\Console\Commands;

use App\Enums\BookStatus;
use App\Models\User;
use App\Notifications\StalledBooksReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Signature('books:send-stalled-reminders {--days=14 : Days without progress before a book counts as stalled}')]
#[Description('Email readers about books they have not made progress on recently')]
class SendStalledBookReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stalledSince = now()->subDays((int) $this->option('days'));

        $stalledBooks = fn (Builder|HasMany $books): Builder|HasMany => $books
            ->where('status', BookStatus::Reading)
            ->where('updated_at', '<', $stalledSince);

        $remindedCount = 0;

        User::query()
            ->where('wants_reading_emails', true)
            ->whereHas('books', $stalledBooks)
            ->with(['books' => fn (HasMany $books) => $stalledBooks($books)->with('author')])
            ->chunkById(100, function ($users) use (&$remindedCount): void {
                foreach ($users as $user) {
                    $user->notify(new StalledBooksReminder($user->books));
                    $remindedCount++;
                }
            });

        $this->components->info("Sent stalled book reminders to {$remindedCount} ".str('reader')->plural($remindedCount).'.');

        return self::SUCCESS;
    }
}
