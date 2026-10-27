<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum NoteType: string implements HasColor, HasIcon, HasLabel
{
    case Note = 'note';
    case Quote = 'quote';
    case Highlight = 'highlight';

    public function getLabel(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Quote => 'Quote',
            self::Highlight => 'Highlight',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Note => 'gray',
            self::Quote => 'info',
            self::Highlight => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Note => Heroicon::OutlinedPencilSquare,
            self::Quote => Heroicon::OutlinedChatBubbleBottomCenterText,
            self::Highlight => Heroicon::OutlinedSparkles,
        };
    }
}
