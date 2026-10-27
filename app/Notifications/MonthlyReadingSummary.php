<?php

namespace App\Notifications;

use App\Models\Book;
use App\Models\ReadingGoal;
use Carbon\CarbonImmutable;
use Filament\Pages\Dashboard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlyReadingSummary extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Book>  $finishedBooks
     */
    public function __construct(
        public CarbonImmutable $month,
        public Collection $finishedBooks,
        public int $pagesRead,
        public ?ReadingGoal $goal = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $monthName = $this->month->format('F Y');
        $finishedCount = $this->finishedBooks->count();

        $message = (new MailMessage)
            ->subject("Your reading in {$monthName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("In {$monthName} you finished {$finishedCount} ".str('book')->plural($finishedCount).' and read about '.number_format($this->pagesRead).' pages.');

        foreach ($this->finishedBooks as $book) {
            $rating = $book->rating === null ? '' : ' — '.str_repeat('★', $book->rating);

            $message->line("• {$book->title} by {$book->author->name}{$rating}");
        }

        if ($this->goal !== null) {
            $message->line("{$this->goal->year} goal: {$this->goal->books_finished} of {$this->goal->target_books} books ({$this->goal->progress_percentage}%).");
        }

        return $message
            ->action('View your dashboard', Dashboard::getUrl(panel: 'admin'))
            ->line('You can turn these emails off from your profile.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'month' => $this->month->format('Y-m'),
            'book_ids' => $this->finishedBooks->modelKeys(),
            'pages_read' => $this->pagesRead,
        ];
    }
}
