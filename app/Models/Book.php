<?php

namespace App\Models;

use App\Enums\BookStatus;
use App\Enums\Genre;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'author', 'author_id', 'cover_path', 'genre', 'status', 'rating', 'summary', 'read_at'])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
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
            'read_at' => 'date',
        ];
    }
}
