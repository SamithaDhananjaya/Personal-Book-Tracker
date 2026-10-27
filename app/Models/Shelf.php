<?php

namespace App\Models;

use Database\Factories\ShelfFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name', 'color'])]
class Shelf extends Model
{
    /** @use HasFactory<ShelfFactory> */
    use HasFactory;

    /**
     * Badge colours a shelf can use.
     *
     * @var array<string, string>
     */
    public const COLORS = [
        'gray' => 'Gray',
        'primary' => 'Green',
        'info' => 'Blue',
        'warning' => 'Amber',
        'danger' => 'Red',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withTimestamps();
    }
}
