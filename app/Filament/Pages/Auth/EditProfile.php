<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                Toggle::make('wants_reading_emails')
                    ->label('Reading reminder emails')
                    ->helperText('A weekly nudge about stalled books and a monthly summary of what you finished.'),
                Section::make('Public profile')
                    ->description('Share what you are reading. Notes and the books on your want-to-read list stay private.')
                    ->schema([
                        Toggle::make('is_profile_public')
                            ->label('Make my bookshelf public')
                            ->live(),
                        TextInput::make('profile_slug')
                            ->label('Profile address')
                            ->prefix(url('/readers').'/')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('profile_slug', filled($state) ? Str::lower($state) : null))
                            ->alphaDash()
                            ->rule('lowercase')
                            ->maxLength(60)
                            ->unique(ignoreRecord: true)
                            ->required(fn (Get $get): bool => (bool) $get('is_profile_public'))
                            ->helperText(fn (): ?string => $this->getUser()->publicProfileUrl()),
                    ]),
            ]);
    }
}
