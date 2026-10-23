<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How much of a day an application covers. A half day is allowed on a
 * one-date application only.
 */
enum LeaveCoverage: string implements HasLabel
{
    case WholeDay = 'whole_day';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function getLabel(): string
    {
        return match ($this) {
            self::WholeDay => 'Whole day',
            self::FirstHalf => 'First half (morning)',
            self::SecondHalf => 'Second half (afternoon)',
        };
    }
}
