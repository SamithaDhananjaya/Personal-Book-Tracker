<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BookStatus: string implements HasColor, HasIcon, HasLabel
{
    case WantToRead = 'want_to_read';
    case Reading = 'reading';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::WantToRead => 'Want to Read',
            self::Reading => 'Reading',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::WantToRead => 'gray',
            self::Reading => 'warning',
            self::Completed => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::WantToRead => Heroicon::OutlinedBookmark,
            self::Reading => Heroicon::OutlinedBookOpen,
            self::Completed => Heroicon::OutlinedCheckCircle,
        };
    }
}
