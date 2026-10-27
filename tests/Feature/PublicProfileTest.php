<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\Book;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_profile_shows_reading_and_finished_books(): void
    {
        $reader = User::factory()->create(['profile_slug' => 'ada', 'is_profile_public' => true]);
        $reading = Book::factory()->reading()->for($reader)->create(['title' => 'Dune', 'total_pages' => 200, 'current_page' => 50]);
        $finished = Book::factory()->completed()->for($reader)->create(['title' => 'Neuromancer', 'rating' => 4]);
        Book::factory()->for($reader)->create(['title' => 'Secret Wishlist Book']);
        Shelf::factory()->for($reader)->hasAttached($finished)->create(['name' => 'Cyberpunk']);
        Shelf::factory()->for($reader)->create(['name' => 'Empty Shelf']);

        $this->get(route('readers.show', 'ada'))
            ->assertOk()
            ->assertSee($reader->name)
            ->assertSee('Dune')
            ->assertSee('25%')
            ->assertSee('Neuromancer')
            ->assertSee('★★★★☆')
            ->assertSee('Cyberpunk')
            ->assertDontSee('Empty Shelf')
            ->assertDontSee('Secret Wishlist Book');
    }

    public function test_private_profiles_are_not_found(): void
    {
        User::factory()->create(['profile_slug' => 'ada', 'is_profile_public' => false]);

        $this->get(route('readers.show', 'ada'))->assertNotFound();
        $this->get(route('readers.show', 'nobody'))->assertNotFound();
    }

    public function test_profile_url_is_only_available_when_public(): void
    {
        $this->assertNull(User::factory()->make(['profile_slug' => 'ada', 'is_profile_public' => false])->publicProfileUrl());
        $this->assertSame(
            route('readers.show', 'ada'),
            User::factory()->make(['profile_slug' => 'ada', 'is_profile_public' => true])->publicProfileUrl(),
        );
    }

    public function test_reader_can_publish_their_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['is_profile_public' => true, 'profile_slug' => 'Ada-Reads'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue($user->is_profile_public);
        $this->assertSame('ada-reads', $user->profile_slug);
    }

    public function test_publishing_requires_a_unique_address(): void
    {
        User::factory()->create(['profile_slug' => 'ada']);
        $this->actingAs(User::factory()->create());

        Livewire::test(EditProfile::class)
            ->fillForm(['is_profile_public' => true, 'profile_slug' => null])
            ->call('save')
            ->assertHasFormErrors(['profile_slug' => 'required']);

        Livewire::test(EditProfile::class)
            ->fillForm(['is_profile_public' => true, 'profile_slug' => 'ADA'])
            ->call('save')
            ->assertHasFormErrors(['profile_slug' => 'unique']);
    }
}
