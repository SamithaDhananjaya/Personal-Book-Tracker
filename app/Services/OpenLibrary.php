<?php

namespace App\Services;

use App\Enums\Genre;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenLibrary
{
    public function __construct(private string $baseUrl = 'https://openlibrary.org') {}

    /**
     * Strip separators so "978-0-441-17271-9" and "9780441172719" match.
     */
    public static function normalizeIsbn(string $isbn): string
    {
        return Str::upper(preg_replace('/[^0-9Xx]/', '', $isbn));
    }

    /**
     * Look up a book's details by ISBN, or null when Open Library has no match.
     *
     * @return array{title: string, authors: list<string>, total_pages: int|null, genre: Genre|null, cover_url: string|null}|null
     *
     * @throws ConnectionException
     */
    public function findByIsbn(string $isbn): ?array
    {
        $isbn = static::normalizeIsbn($isbn);
        $key = "ISBN:{$isbn}";

        $book = $this->client()
            ->get('/api/books', [
                'bibkeys' => $key,
                'format' => 'json',
                'jscmd' => 'data',
            ])
            ->throw()
            ->json($key);

        if (! is_array($book) || blank($book['title'] ?? null)) {
            return null;
        }

        return [
            'title' => $book['title'],
            'authors' => collect($book['authors'] ?? [])->pluck('name')->filter()->values()->all(),
            'total_pages' => isset($book['number_of_pages']) ? (int) $book['number_of_pages'] : null,
            'genre' => $this->guessGenre(collect($book['subjects'] ?? [])->pluck('name')->all()),
            'cover_url' => $book['cover']['large'] ?? $book['cover']['medium'] ?? null,
        ];
    }

    /**
     * Download a cover image to the public disk and return its stored path.
     */
    public function downloadCover(string $coverUrl, string $isbn): ?string
    {
        try {
            $response = Http::timeout(10)->get($coverUrl);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            return null;
        }

        $path = 'book-covers/'.static::normalizeIsbn($isbn).'-'.Str::random(8).'.jpg';

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * Map Open Library subjects onto the app's genres.
     *
     * @param  list<string>  $subjects
     */
    public function guessGenre(array $subjects): ?Genre
    {
        $subjects = Str::lower(implode(' | ', $subjects));

        return match (true) {
            $subjects === '' => null,
            Str::contains($subjects, ['science fiction', 'sci-fi']) => Genre::SciFi,
            Str::contains($subjects, ['computer', 'programming', 'software', 'technology']) => Genre::Tech,
            Str::contains($subjects, ['nonfiction', 'non-fiction']) => Genre::NonFiction,
            Str::contains($subjects, 'fiction') => Genre::Fiction,
            default => Genre::NonFiction,
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, throw: false);
    }
}
