<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Claude-powered helpers for writing book summaries and suggesting what to read next.
 */
class ReadingAssistant
{
    public function __construct(
        private ?string $apiKey,
        private string $model = 'claude-opus-5-5',
        private ?Client $client = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Write a short spoiler-free summary, or null when Claude doesn't know the book.
     */
    public function summarize(string $title, ?string $author): ?string
    {
        $book = $author ? "\"{$title}\" by {$author}" : "\"{$title}\"";

        $text = $this->ask(
            system: 'You write concise, spoiler-free book summaries for a personal reading tracker. '
                .'Reply with the summary only: 3-4 sentences of plain prose, no headings, no lists, no preamble. '
                .'If you do not recognise the book, reply with exactly UNKNOWN.',
            prompt: "Summarize {$book}.",
            effort: 'low',
        );

        if ($text === null || trim($text) === 'UNKNOWN') {
            return null;
        }

        return trim($text);
    }

    /**
     * Suggest books to read next based on the reader's highest rated and queued books.
     *
     * @return list<array{title: string, author: string, reason: string, genre: string}>
     */
    public function suggestNextReads(User $reader, int $count = 5): array
    {
        $favourites = $reader->books()
            ->with('author')
            ->where('status', BookStatus::Completed)
            ->where('rating', '>=', 4)
            ->latest('read_at')
            ->limit(15)
            ->get();

        if ($favourites->isEmpty()) {
            return [];
        }

        $knownTitles = $reader->books()->with('author')->get()
            ->map(fn (Book $book): string => "- {$book->title} by {$book->author->name}")
            ->implode("\n");

        $favouriteList = $favourites
            ->map(fn (Book $book): string => "- {$book->title} by {$book->author->name} ({$book->rating}/5, {$book->genre->getLabel()})")
            ->implode("\n");

        $text = $this->ask(
            system: 'You recommend books for a personal reading tracker. Recommend real, published books only. '
                .'Each reason is one sentence that connects the suggestion to the reader\'s favourites.',
            prompt: "Books I rated highly:\n{$favouriteList}\n\n"
                ."Books already in my library (do not suggest these):\n{$knownTitles}\n\n"
                ."Suggest {$count} books I should read next.",
            effort: 'medium',
            schema: [
                'type' => 'object',
                'properties' => [
                    'suggestions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'author' => ['type' => 'string'],
                                'reason' => ['type' => 'string'],
                                'genre' => [
                                    'type' => 'string',
                                    'enum' => array_map(fn (Genre $genre): string => $genre->value, Genre::cases()),
                                ],
                            ],
                            'required' => ['title', 'author', 'reason', 'genre'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['suggestions'],
                'additionalProperties' => false,
            ],
        );

        $suggestions = json_decode((string) $text, true)['suggestions'] ?? [];

        return collect($suggestions)
            ->filter(fn (mixed $suggestion): bool => is_array($suggestion) && filled($suggestion['title'] ?? null))
            ->map(fn (array $suggestion): array => [
                'title' => (string) $suggestion['title'],
                'author' => (string) ($suggestion['author'] ?? ''),
                'reason' => (string) ($suggestion['reason'] ?? ''),
                'genre' => (Genre::tryFrom((string) ($suggestion['genre'] ?? '')) ?? Genre::Fiction)->value,
            ])
            ->take($count)
            ->values()
            ->all();
    }

    /**
     * Send one request and return the response text, or null when it failed or was declined.
     *
     * @param  array<string, mixed>|null  $schema
     */
    private function ask(string $system, string $prompt, string $effort, ?array $schema = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $outputConfig = ['effort' => $effort];

        if ($schema !== null) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $schema];
        }

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->model,
                fallbacks: 'default',
                outputConfig: $outputConfig,
                system: $system,
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $exception) {
            Log::warning('Claude request failed', ['message' => $exception->getMessage()]);

            return null;
        }

        return $this->textOf($message);
    }

    private function textOf(BetaMessage $message): ?string
    {
        if ($message->stopReason === 'refusal') {
            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return null;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}
