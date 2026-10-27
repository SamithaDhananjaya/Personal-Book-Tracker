<?php

namespace App\Http\Controllers;

use App\Enums\BookStatus;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Contracts\View\View;

class PublicProfileController extends Controller
{
    /**
     * Show a reader's public bookshelf.
     */
    public function __invoke(string $profileSlug): View
    {
        $reader = User::query()
            ->where('profile_slug', $profileSlug)
            ->where('is_profile_public', true)
            ->firstOrFail();

        $books = $reader->books()->with('author');

        return view('readers.show', [
            'reader' => $reader,
            'currentlyReading' => (clone $books)
                ->where('status', BookStatus::Reading)
                ->latest('updated_at')
                ->get(),
            'recentlyFinished' => (clone $books)
                ->where('status', BookStatus::Completed)
                ->latest('read_at')
                ->limit(12)
                ->get(),
            'finishedThisYear' => (clone $books)
                ->where('status', BookStatus::Completed)
                ->whereYear('read_at', now()->year)
                ->count(),
            'goal' => $reader->readingGoals()->where('year', now()->year)->first(),
            'shelves' => $reader->shelves()
                ->withCount('books')
                ->orderBy('name')
                ->get()
                ->filter(fn (Shelf $shelf): bool => $shelf->books_count > 0),
        ]);
    }
}
