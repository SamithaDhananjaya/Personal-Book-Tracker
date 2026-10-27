<?php

namespace App\Models;

use App\Enums\BookStatus;
use Database\Factories\ReadingGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'year', 'target_books'])]
class ReadingGoal extends Model
{
    /** @use HasFactory<ReadingGoalFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Number of books the owner finished during the goal's year.
     *
     * @return Attribute<int, never>
     */
    protected function booksFinished(): Attribute
    {
        return Attribute::get(fn (): int => $this->user->books()
            ->where('status', BookStatus::Completed)
            ->whereYear('read_at', $this->year)
            ->count())->shouldCache();
    }

    /**
     * Percentage of the target reached, capped at 100.
     *
     * @return Attribute<int, never>
     */
    protected function progressPercentage(): Attribute
    {
        return Attribute::get(fn (): int => (int) min(100, round($this->books_finished / $this->target_books * 100)));
    }

    /**
     * Books the reader should have finished by today to stay on pace for the year.
     *
     * @return Attribute<int, never>
     */
    protected function expectedByToday(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->year < now()->year) {
                return $this->target_books;
            }

            if ($this->year > now()->year) {
                return 0;
            }

            return (int) floor($this->target_books * now()->dayOfYear / now()->daysInYear);
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
            'year' => 'integer',
            'target_books' => 'integer',
        ];
    }
}
