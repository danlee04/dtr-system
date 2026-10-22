<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What kind of non-working day an entry on the holiday list is. A special
 * working day is an ordinary work day, so it never gets an entry.
 */
enum HolidayType: string implements HasLabel
{
    case Regular = 'regular';
    case Special = 'special';
    case Suspension = 'suspension';

    public function getLabel(): string
    {
        return match ($this) {
            self::Regular => 'Regular holiday',
            self::Special => 'Special non-working day',
            self::Suspension => 'Work suspension',
        };
    }
}
