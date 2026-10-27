<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_authors_of_other_users_are_hidden(): void
    {
        $ownAuthor = Author::factory()->for($this->user)->create();
        $otherAuthor = Author::factory()->create();

        Livewire::test(ListAuthors::class)
            ->assertCanSeeTableRecords([$ownAuthor])
            ->assertCanNotSeeTableRecords([$otherAuthor]);

        $this->get(AuthorResource::getUrl('edit', ['record' => $otherAuthor]))->assertNotFound();
    }

    public function test_created_author_belongs_to_signed_in_user(): void
    {
        Livewire::test(CreateAuthor::class)
            ->fillForm(['name' => 'Frank Herbert'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Author::class, [
            'name' => 'Frank Herbert',
            'user_id' => $this->user->id,
        ]);
    }
}
