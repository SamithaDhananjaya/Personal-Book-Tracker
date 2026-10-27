<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Every registered reader may use the panel; records are scoped per user.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    public function readingGoals(): HasMany
    {
        return $this->hasMany(ReadingGoal::class);
    }

    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
