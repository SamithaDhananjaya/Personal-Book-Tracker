<?php

namespace App\Notifications;

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StalledBooksReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Book>  $stalledBooks
     */
    public function __construct(public Collection $stalledBooks) {}

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
        $message = (new MailMessage)
            ->subject('Pick up where you left off')
            ->greeting("Hi {$notifiable->name},")
            ->line('These books have been waiting on your nightstand for a while:');

        foreach ($this->stalledBooks as $book) {
            $progress = $book->progress_percentage === null ? '' : " ({$book->progress_percentage}% read)";

            $message->line("• {$book->title} by {$book->author->name}{$progress}");
        }

        return $message
            ->action('Log your progress', BookResource::getUrl(panel: 'admin'))
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
            'book_ids' => $this->stalledBooks->modelKeys(),
        ];
    }
}
