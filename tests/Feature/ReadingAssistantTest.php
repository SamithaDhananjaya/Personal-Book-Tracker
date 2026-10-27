<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Enums\BookStatus;
use App\Enums\Genre;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use App\Services\ReadingAssistant;
use Filament\Actions\Testing\TestAction;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class ReadingAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * Request bodies sent through the fake transporter.
     *
     * @var list<array<string, mixed>>
     */
    private array $sentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_assistant_is_not_configured_without_an_api_key(): void
    {
        $assistant = new ReadingAssistant(apiKey: null);

        $this->assertFalse($assistant->isConfigured());
        $this->assertNull($assistant->summarize('Dune', 'Frank Herbert'));
    }

    public function test_summary_request_uses_the_configured_model_with_fallbacks(): void
    {
        $assistant = $this->assistantReplying(['stop_reason' => 'end_turn', 'text' => 'A desert planet saga.']);

        $this->assertSame('A desert planet saga.', $assistant->summarize('Dune', 'Frank Herbert'));

        $request = $this->sentRequests[0];
        $this->assertSame('claude-opus-5-5', $request['model']);
        $this->assertSame('default', $request['fallbacks']);
        $this->assertSame('low', $request['output_config']['effort']);
        $this->assertStringContainsString('"Dune" by Frank Herbert', $request['messages'][0]['content']);
    }

    public function test_unknown_or_refused_summaries_return_null(): void
    {
        $this->assertNull($this->assistantReplying(['stop_reason' => 'end_turn', 'text' => 'UNKNOWN'])->summarize('Zzz', null));
        $this->assertNull($this->assistantReplying(['stop_reason' => 'refusal', 'text' => ''])->summarize('Zzz', null));
    }

    public function test_api_errors_return_null(): void
    {
        $assistant = $this->assistantReplying(['status' => 400]);

        $this->assertNull($assistant->summarize('Dune', 'Frank Herbert'));
    }

    public function test_suggestions_use_favourites_and_structured_output(): void
    {
        Book::factory()->completed()->for($this->user)->create(['title' => 'Dune', 'rating' => 5]);
        Book::factory()->completed()->for($this->user)->create(['title' => 'Meh Book', 'rating' => 2]);

        $assistant = $this->assistantReplying(['stop_reason' => 'end_turn', 'text' => json_encode(['suggestions' => [
            ['title' => 'Hyperion', 'author' => 'Dan Simmons', 'reason' => 'Epic SF like Dune.', 'genre' => 'sci_fi'],
            ['title' => '', 'author' => 'Nobody', 'reason' => 'Invalid', 'genre' => 'fiction'],
        ]])]);

        $suggestions = $assistant->suggestNextReads($this->user);

        $this->assertSame([
            ['title' => 'Hyperion', 'author' => 'Dan Simmons', 'reason' => 'Epic SF like Dune.', 'genre' => 'sci_fi'],
        ], $suggestions);

        $request = $this->sentRequests[0];
        $this->assertSame('json_schema', $request['output_config']['format']['type']);
        $this->assertStringContainsString('Dune', $request['messages'][0]['content']);
        $this->assertStringNotContainsString('Meh Book (2/5', $request['messages'][0]['content']);
    }

    public function test_no_suggestions_are_requested_without_favourites(): void
    {
        $assistant = $this->assistantReplying(['stop_reason' => 'end_turn', 'text' => '{}']);

        $this->assertSame([], $assistant->suggestNextReads($this->user));
        $this->assertSame([], $this->sentRequests);
    }

    public function test_ai_actions_are_hidden_without_an_api_key(): void
    {
        $this->app->instance(ReadingAssistant::class, new ReadingAssistant(apiKey: null));

        Livewire::test(ListBooks::class)->assertActionHidden('suggestNextReads');
        Livewire::test(CreateBook::class)->assertActionDoesNotExist(TestAction::make('generateSummary')->schemaComponent('summary'));
    }

    public function test_generate_summary_fills_the_summary_field(): void
    {
        $author = Author::factory()->for($this->user)->create(['name' => 'Frank Herbert']);

        $this->mock(ReadingAssistant::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('summarize')->with('Dune', 'Frank Herbert')->once()->andReturn('A desert planet saga.');
        });

        Livewire::test(CreateBook::class)
            ->fillForm(['title' => 'Dune', 'author_id' => $author->id])
            ->callAction(TestAction::make('generateSummary')->schemaComponent('summary'))
            ->assertSchemaStateSet(['summary' => 'A desert planet saga.']);
    }

    public function test_selected_suggestions_are_added_to_want_to_read(): void
    {
        $this->mock(ReadingAssistant::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('suggestNextReads')->once()->andReturn([
                ['title' => 'Hyperion', 'author' => 'Dan Simmons', 'reason' => 'Epic SF.', 'genre' => 'sci_fi'],
                ['title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'reason' => 'Big ideas.', 'genre' => 'non_fiction'],
            ]);
        });

        Livewire::test(ListBooks::class)
            ->callAction('suggestNextReads', ['selected' => [0]])
            ->assertHasNoFormErrors()
            ->assertNotified('Added 1 book to your want-to-read list');

        $book = $this->user->books()->sole();
        $this->assertSame('Hyperion', $book->title);
        $this->assertSame('Dan Simmons', $book->author->name);
        $this->assertSame(Genre::SciFi, $book->genre);
        $this->assertSame(BookStatus::WantToRead, $book->status);
    }

    public function test_suggestions_modal_is_cancelled_when_there_is_nothing_to_suggest(): void
    {
        $this->mock(ReadingAssistant::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('suggestNextReads')->once()->andReturn([]);
        });

        Livewire::test(ListBooks::class)
            ->mountAction('suggestNextReads')
            ->assertNotified('No suggestions yet')
            ->assertActionNotMounted('suggestNextReads');
    }

    /**
     * Build an assistant whose SDK client talks to a fake transporter returning one canned response.
     *
     * @param  array{stop_reason?: string, text?: string, status?: int}  $reply
     */
    private function assistantReplying(array $reply): ReadingAssistant
    {
        $transporter = new class($reply, $this->sentRequests) implements ClientInterface
        {
            /**
             * @param  array{stop_reason?: string, text?: string, status?: int}  $reply
             * @param  list<array<string, mixed>>  $sentRequests
             */
            public function __construct(private array $reply, private array &$sentRequests) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sentRequests[] = json_decode((string) $request->getBody(), true);

                if (isset($this->reply['status'])) {
                    return new Response($this->reply['status'], ['Content-Type' => 'application/json'], json_encode([
                        'type' => 'error',
                        'error' => ['type' => 'invalid_request_error', 'message' => 'Bad request'],
                    ]));
                }

                return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'id' => 'msg_test',
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => 'claude-opus-5-5',
                    'content' => [['type' => 'text', 'text' => $this->reply['text'] ?? '']],
                    'stop_reason' => $this->reply['stop_reason'] ?? 'end_turn',
                    'stop_sequence' => null,
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]));
            }
        };

        return new ReadingAssistant(
            apiKey: 'test-key',
            client: new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter, 'maxRetries' => 0]),
        );
    }
}
