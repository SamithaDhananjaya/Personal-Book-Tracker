<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Genre: string implements HasColor, HasLabel
{
    case Fiction = 'fiction';
    case NonFiction = 'non_fiction';
    case Tech = 'tech';
    case SciFi = 'sci_fi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fiction => 'Fiction',
            self::NonFiction => 'Non-Fiction',
            self::Tech => 'Tech',
            self::SciFi => 'Sci-Fi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fiction => 'info',
            self::NonFiction => 'warning',
            self::Tech => 'success',
            self::SciFi => 'danger',
        };
    }
}
