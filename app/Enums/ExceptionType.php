<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Work away from the office, on a signed slip. Both spare the day from late,
 * undertime and absence; they differ only in the paper behind them.
 */
enum ExceptionType: string implements HasLabel
{
    case OfficialBusiness = 'official_business';
    case TravelOrder = 'travel_order';

    public function getLabel(): string
    {
        return match ($this) {
            self::OfficialBusiness => 'Official business',
            self::TravelOrder => 'Travel order',
        };
    }
}
