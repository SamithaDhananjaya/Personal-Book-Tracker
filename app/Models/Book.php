<?php

namespace App\Models;

use App\Enums\BookStatus;
use App\Enums\Genre;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'author_id', 'cover_path', 'genre', 'status', 'rating', 'summary', 'total_pages', 'current_page', 'read_at'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    /**
     * Keep the read date and page count consistent with a completed status.
     */
    protected static function booted(): void
    {
        static::saving(function (Book $book): void {
            $book->current_page ??= 0;

            if ($book->status !== BookStatus::Completed) {
                return;
            }

            $book->read_at ??= now();

            if ($book->total_pages !== null) {
                $book->current_page = $book->total_pages;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Log a new page position, completing the book once the last page is reached.
     */
    public function logProgress(int $currentPage): void
    {
        $this->current_page = $currentPage;

        if ($this->total_pages !== null && $currentPage >= $this->total_pages) {
            $this->status = BookStatus::Completed;
        } elseif ($this->status === BookStatus::WantToRead && $currentPage > 0) {
            $this->status = BookStatus::Reading;
        }

        $this->save();
    }

    /**
     * Percentage of the book read, or null when the page count is unknown.
     *
     * @return Attribute<int|null, never>
     */
    protected function progressPercentage(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->total_pages) {
                return null;
            }

            return (int) min(100, round($this->current_page / $this->total_pages * 100));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'genre' => Genre::class,
            'status' => BookStatus::class,
            'rating' => 'integer',
            'total_pages' => 'integer',
            'current_page' => 'integer',
            'read_at' => 'date',
        ];
    }
}
